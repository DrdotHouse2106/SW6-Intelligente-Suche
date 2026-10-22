<?php declare(strict_types=1);

namespace Nova\Search\Subscriber;

use Nova\Search\Rewrite\RewriteService;
use Nova\Search\Search\LearningProductSearchBuilder;
use Nova\Search\Service\RewriteStore;
use Nova\Search\Service\SearchStatistics;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Suchergebnisseite (Enter im Suchfeld):
 *  - zählt die Suche (die Seite wird nicht HTTP-gecacht, daher serverseitig zuverlässig)
 *  - merkt Begriffe ohne Treffer für die Claude-Auswertung vor
 *  - optional: wertet einen neuen Begriff sofort aus (kurzer Timeout) und zeigt direkt passende Treffer
 */
class SearchPageSubscriber implements EventSubscriberInterface
{
    private const SYNC_TIMEOUT = 4.0;

    public function __construct(
        private readonly SearchStatistics $statistics,
        private readonly RewriteStore $rewrites,
        private readonly RewriteService $rewriteService,
        private readonly AbstractProductSearchRoute $productSearchRoute,
        private readonly SystemConfigService $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [SearchPageLoadedEvent::class => 'onSearchPage'];
    }

    public function onSearchPage(SearchPageLoadedEvent $event): void
    {
        $request = $event->getRequest();
        // Nur der eigentliche Seitenaufruf – nicht das Nachladen beim Blättern/Filtern
        if ($request->attributes->get('_route') !== 'frontend.search.page' || (int) $request->query->get('p', 1) > 1) {
            return;
        }

        $context = $event->getSalesChannelContext();
        $salesChannelId = $context->getSalesChannelId();
        $page = $event->getPage();
        $term = $page->getSearchTerm();
        $listing = $page->getListing();
        $rewritten = $listing->getCriteria()->hasExtension(LearningProductSearchBuilder::EXTENSION);

        try {
            if ($this->config->get('NovaSearch.config.learningActive', $salesChannelId) !== false) {
                $this->statistics->recordSearch($salesChannelId, $term, $listing->getTotal());
            }

            // Treffer, Umschreibung oder Nummernsuche → nichts für Claude vormerken
            if ($listing->getTotal() > 0 || $rewritten || $listing->getCriteria()->hasExtension(LearningProductSearchBuilder::NUMBER_EXTENSION)) {
                return;
            }

            $known = $this->rewrites->isKnown($salesChannelId, $term);
            $this->rewrites->markPending($salesChannelId, $term);

            // Sofort-Auswertung nur für neue Begriffe – jeder Begriff wird höchstens einmal angefragt
            if ($known || !$this->config->getBool('NovaSearch.config.claudeSyncOnSearchPage', $salesChannelId)) {
                return;
            }

            if ($this->rewriteService->rewriteNow($term, $context, self::SYNC_TIMEOUT) === null) {
                return;
            }

            // Mit der neuen Umschreibung erneut suchen (der Such-Builder wendet sie an)
            $page->setListing($this->productSearchRoute->load($request, $context, new Criteria())->getListingResult());
        } catch (\Throwable $e) {
            $this->logger->warning('Nova Suche: Auswertung der Suchseite fehlgeschlagen', ['exception' => $e]);
        }
    }
}
