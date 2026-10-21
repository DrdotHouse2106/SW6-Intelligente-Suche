<?php declare(strict_types=1);

namespace Nova\Search\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Verarbeitet Nutzungssignale aus Suchfenster (Storefront) und App (Store-API) einheitlich:
 *  - search:   Suchbegriff + Trefferanzahl („total“, im Storefront „hits“)
 *  - click:    Produkt aus den Suchergebnissen angeklickt
 *  - cart:     Produkt nach einer Suche in den Warenkorb gelegt
 *  - purchase: Produkt nach einer Suche gekauft – nur, wenn der aktuelle Kunde dieses Produkt
 *              tatsächlich kürzlich bestellt hat (Schutz vor erfundenen Käufen)
 *
 * Gespeichert werden nur Begriff, Produkt-ID und Zähler – keine Kunden-, Sitzungs- oder Bestelldaten.
 */
class EventRecorder
{
    public const MAX_EVENTS_PER_REQUEST = 20;
    public const TYPE_SEARCH = 'search';
    public const TYPE_PURCHASE = 'purchase';

    /** Wie lange nach einer Bestellung ein Kauf noch gemeldet werden darf */
    private const PURCHASE_WINDOW_HOURS = 48;

    public function __construct(
        private readonly SearchStatistics $statistics,
        private readonly RewriteStore $rewrites,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Wandelt den rohen Request-Inhalt in eine Liste von Ereignissen (max. 20, nur Arrays).
     *
     * @return list<array<string, mixed>>
     */
    public static function parse(mixed $events): array
    {
        if (!\is_array($events)) {
            return [];
        }

        $list = array_values(array_filter(array_values($events), static fn ($event): bool => \is_array($event)));

        /** @var list<array<string, mixed>> $list */
        return \array_slice($list, 0, self::MAX_EVENTS_PER_REQUEST);
    }

    /**
     * @param list<array<string, mixed>> $events
     * @param string|null $customerId nur gesetzt, wenn Käufe erlaubt sind (Store-API mit angemeldetem Kunden/Gast)
     *
     * @return int Anzahl gespeicherter Ereignisse
     */
    public function record(string $salesChannelId, array $events, ?string $customerId = null): int
    {
        if ($events === []) {
            return 0;
        }

        $productIds = array_values(array_filter(array_map(
            static fn (array $event): string => \is_string($event['productId'] ?? null) ? strtolower($event['productId']) : '',
            $events
        ), Uuid::isValid(...)));

        $saved = 0;

        try {
            $existing = array_flip($this->statistics->existingProductIds($productIds));

            $ordered = [];
            if ($customerId !== null && Uuid::isValid($customerId)
                && array_filter($events, static fn (array $e): bool => ($e['type'] ?? '') === self::TYPE_PURCHASE) !== []) {
                $ordered = array_flip($this->statistics->recentlyOrderedProductIds($customerId, $productIds, self::PURCHASE_WINDOW_HOURS));
            }

            foreach ($events as $event) {
                $term = \is_string($event['term'] ?? null) ? $event['term'] : '';
                $type = \is_string($event['type'] ?? null) ? $event['type'] : '';
                if ($term === '') {
                    continue;
                }

                if ($type === self::TYPE_SEARCH) {
                    $hits = $event['total'] ?? $event['hits'] ?? 0;
                    $hits = \is_numeric($hits) ? (int) $hits : 0;
                    $this->statistics->recordSearch($salesChannelId, $term, $hits);
                    if ($hits === 0 && empty($event['rewritten'])) {
                        $this->rewrites->markPending($salesChannelId, $term);
                    }
                    ++$saved;
                    continue;
                }

                $productId = \is_string($event['productId'] ?? null) ? strtolower($event['productId']) : '';
                if (!isset($existing[$productId])) {
                    continue;
                }

                $statisticType = match ($type) {
                    SearchStatistics::TYPE_CLICK => SearchStatistics::TYPE_CLICK,
                    SearchStatistics::TYPE_CART => SearchStatistics::TYPE_CART,
                    self::TYPE_PURCHASE => isset($ordered[$productId]) ? SearchStatistics::TYPE_ORDER : null,
                    default => null,
                };

                if ($statisticType !== null) {
                    $this->statistics->recordProduct($salesChannelId, $term, $productId, $statisticType);
                    ++$saved;
                }
            }
        } catch (\Throwable $e) {
            // Erfassung ist optional – Fehler nie an Besucher oder App weitergeben
            $this->logger->warning('Nova Suche: Ereignisse konnten nicht gespeichert werden', ['exception' => $e]);
        }

        return $saved;
    }
}
