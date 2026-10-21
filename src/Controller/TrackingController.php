<?php declare(strict_types=1);

namespace Nova\Search\Controller;

use Nova\Search\Service\RewriteStore;
use Nova\Search\Service\SearchStatistics;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Nimmt anonyme Nutzungssignale aus dem Suchfenster entgegen (per navigator.sendBeacon):
 *  - search: Suchbegriff + Trefferanzahl (nach einer Tipp-Pause)
 *  - click:  Klick auf ein Produkt aus den Suchergebnissen
 *  - cart:   Produkt aus einer Suche wurde in den Warenkorb gelegt
 *
 * Es werden weder IP-Adressen noch Kunden- oder Sitzungs-IDs gespeichert.
 * Schutz vor Missbrauch: max. 20 Ereignisse pro Aufruf, max. 300 pro Sitzung und Stunde,
 * nur bekannte Ereignistypen, nur existierende Produkt-IDs, Begriffe werden normalisiert.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class TrackingController extends StorefrontController
{
    private const MAX_EVENTS_PER_REQUEST = 20;
    private const MAX_EVENTS_PER_HOUR = 300;
    private const MAX_BODY_BYTES = 8192;
    private const SESSION_KEY = 'nova-search-track';

    public function __construct(
        private readonly SearchStatistics $statistics,
        private readonly RewriteStore $rewrites,
        private readonly SystemConfigService $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/nova-search/track',
        name: 'frontend.nova-search.track',
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function track(Request $request, SalesChannelContext $context): Response
    {
        $salesChannelId = $context->getSalesChannelId();
        if ($this->config->get('NovaSearch.config.learningActive', $salesChannelId) === false) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $body = (string) $request->getContent();
        $data = \strlen($body) <= self::MAX_BODY_BYTES ? json_decode($body, true) : null;
        $events = \is_array($data) && \is_array($data['events'] ?? null) ? \array_slice($data['events'], 0, self::MAX_EVENTS_PER_REQUEST) : [];

        $events = array_values(array_filter($events, fn ($event): bool => \is_array($event) && $this->allow($request)));
        if ($events === []) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        // Nur Produkte, die es wirklich gibt
        $productIds = array_values(array_filter(array_map(
            static fn (array $event): string => \is_string($event['productId'] ?? null) ? strtolower($event['productId']) : '',
            $events
        ), Uuid::isValid(...)));

        try {
            $existing = array_flip($this->statistics->existingProductIds($productIds));

            foreach ($events as $event) {
                $term = \is_string($event['term'] ?? null) ? $event['term'] : '';
                if ($term === '') {
                    continue;
                }

                $type = $event['type'] ?? '';
                if ($type === 'search') {
                    $hits = \is_numeric($event['hits'] ?? null) ? (int) $event['hits'] : 0;
                    $this->statistics->recordSearch($salesChannelId, $term, $hits);
                    if ($hits === 0 && empty($event['rewritten'])) {
                        $this->rewrites->markPending($salesChannelId, $term);
                    }
                    continue;
                }

                $productId = \is_string($event['productId'] ?? null) ? strtolower($event['productId']) : '';
                if (isset($existing[$productId]) && \in_array($type, [SearchStatistics::TYPE_CLICK, SearchStatistics::TYPE_CART], true)) {
                    $this->statistics->recordProduct($salesChannelId, $term, $productId, $type);
                }
            }
        } catch (\Throwable $e) {
            // Erfassung ist optional – Fehler nie an den Besucher weitergeben
            $this->logger->warning('Nova Suche: Ereignisse konnten nicht gespeichert werden', ['exception' => $e]);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** Einfache Begrenzung pro Sitzung (gleitendes Stundenfenster) */
    private function allow(Request $request): bool
    {
        if (!$request->hasSession()) {
            return true;
        }

        $session = $request->getSession();
        $state = $session->get(self::SESSION_KEY);
        $now = time();

        if (!\is_array($state) || ($state['since'] ?? 0) < $now - 3600) {
            $state = ['since' => $now, 'count' => 0];
        }

        if ($state['count'] >= self::MAX_EVENTS_PER_HOUR) {
            return false;
        }

        ++$state['count'];
        $session->set(self::SESSION_KEY, $state);

        return true;
    }
}
