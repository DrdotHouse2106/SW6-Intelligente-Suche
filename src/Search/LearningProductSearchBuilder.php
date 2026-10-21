<?php declare(strict_types=1);

namespace Nova\Search\Search;

use Nova\Search\Service\RewriteStore;
use Nova\Search\Service\SearchStatistics;
use Nova\Search\Service\TermNormalizer;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Erweitert Shopwares Produktsuche (Suchseite, Suchfenster, Vorschläge) um zwei Dinge:
 *
 * 1. Umschreibung: Für Begriffe ohne Treffer, zu denen eine geprüfte Umschreibung gespeichert ist
 *    (z. B. „bremsbelag ente“ → „bremsbelag 2cv“), wird direkt mit der Umschreibung gesucht.
 *    Die Criteria bekommt dann die Extension „novaSearchRewrite“ für den Hinweis „Ergebnisse für …“.
 *
 * 2. Lernen: Produkte, die bei genau diesem Begriff oft geklickt, in den Warenkorb gelegt oder
 *    gekauft wurden, bekommen zusätzliche Relevanz-Punkte. Das wirkt nur auf die Sortierung
 *    nach Relevanz – die Trefferliste selbst bleibt unverändert, weil Shopwares Suchwort-Filter
 *    weiterhin gelten.
 */
class LearningProductSearchBuilder implements ProductSearchBuilderInterface
{
    public const EXTENSION = 'novaSearchRewrite';
    /** Request-Attribut, um Umschreibungen gezielt zu umgehen (z. B. beim erneuten Prüfen) */
    public const SKIP_REWRITE = 'nova-search-skip-rewrite';

    public function __construct(
        private readonly ProductSearchBuilderInterface $decorated,
        private readonly RewriteStore $rewrites,
        private readonly SearchStatistics $statistics,
        private readonly SystemConfigService $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function build(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $search = RequestParamHelper::get($request, 'search');
        $original = \is_array($search) ? implode(' ', $search) : (string) $search;
        $salesChannelId = $context->getSalesChannelId();

        $rewrite = null;
        if (!$request->attributes->getBoolean(self::SKIP_REWRITE) && trim($original) !== '') {
            try {
                $rewrite = $this->rewrites->find($salesChannelId, $original);
            } catch (\Throwable $e) {
                // Datenbankfehler dürfen die Suche nie verhindern
                $this->logger->warning('Nova Suche: Umschreibung konnte nicht gelesen werden', ['exception' => $e]);
            }
        }

        if ($rewrite !== null && $rewrite !== TermNormalizer::normalize($original)) {
            $rewritten = clone $request;
            $rewritten->query->set('search', $rewrite);
            if ($rewritten->request->has('search')) {
                $rewritten->request->set('search', $rewrite);
            }

            $this->decorated->build($rewritten, $criteria, $context);
            $criteria->addExtension(self::EXTENSION, new ArrayStruct(['original' => trim($original), 'rewrite' => $rewrite]));
        } else {
            $this->decorated->build($request, $criteria, $context);
        }

        $this->addLearnedBoost($original, $criteria, $context);
    }

    private function addLearnedBoost(string $term, Criteria $criteria, SalesChannelContext $context): void
    {
        $salesChannelId = $context->getSalesChannelId();

        // Nur wenn Shopware mit Suchwort-Bewertung arbeitet (Standard). Ohne diese Bewertungs-Queries
        // würden zusätzliche Queries die Trefferliste auf die gelernten Produkte einschränken.
        $queries = $criteria->getQueries();
        if ($queries === [] || $criteria->getTerm() !== null
            || $this->config->get('NovaSearch.config.learningActive', $salesChannelId) === false) {
            return;
        }

        try {
            $learned = $this->statistics->learnedProducts(
                $salesChannelId,
                $term,
                max(1, $this->config->getInt('NovaSearch.config.boostMinScore', $salesChannelId) ?: 3)
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Nova Suche: Gelernte Produkte konnten nicht gelesen werden', ['exception' => $e]);

            return;
        }

        if ($learned === []) {
            return;
        }

        // Die Punkte orientieren sich an der stärksten vorhandenen Bewertung, damit der Boost spürbar ist
        // und Shopwares Mindestpunktzahl (kleinste Query-Bewertung) nicht absinkt.
        $base = max(array_map(static fn (ScoreQuery $query): float => $query->getScore(), $queries));
        $strongest = max($learned);

        foreach ($learned as $productId => $score) {
            // 1,0 … 2,0-fache Bewertung, logarithmisch nach Lernwert
            $factor = 1 + log(1 + $score) / log(1 + $strongest);
            $criteria->addQuery(new ScoreQuery(new EqualsFilter('product.id', $productId), $base * $factor));
        }
    }
}
