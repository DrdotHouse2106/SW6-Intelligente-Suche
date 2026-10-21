<?php declare(strict_types=1);

namespace Nova\Search\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Zählt Suchen, Klicks, Warenkorb-Aktionen und Käufe je Suchbegriff (anonym, ohne Personenbezug)
 * und liefert daraus beliebte Suchanfragen und die „gelernten“ Produkte für einen Begriff.
 */
class SearchStatistics
{
    public const TYPE_CLICK = 'click';
    public const TYPE_CART = 'cart';
    public const TYPE_ORDER = 'order';

    private const COLUMNS = [
        self::TYPE_CLICK => 'clicks',
        self::TYPE_CART => 'carts',
        self::TYPE_ORDER => 'orders',
    ];

    /** Gewichtung der Signale: Kauf > Warenkorb > Klick */
    private const WEIGHTS = ['clicks' => 1, 'carts' => 3, 'orders' => 5];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function recordSearch(string $salesChannelId, string $term, int $hits): void
    {
        $term = TermNormalizer::normalize($term);
        if (!TermNormalizer::isTrackable($term)) {
            return;
        }

        $this->connection->executeStatement(
            'INSERT INTO `nova_search_term` (`sales_channel_id`, `term`, `searches`, `last_hits`, `created_at`, `updated_at`)
             VALUES (:sc, :term, 1, :hits, :now, :now)
             ON DUPLICATE KEY UPDATE `searches` = `searches` + 1, `last_hits` = VALUES(`last_hits`), `updated_at` = VALUES(`updated_at`)',
            [
                'sc' => Uuid::fromHexToBytes($salesChannelId),
                'term' => $term,
                'hits' => max(0, min($hits, 1_000_000)),
                'now' => $this->now(),
            ]
        );
    }

    public function recordProduct(string $salesChannelId, string $term, string $productId, string $type): void
    {
        $term = TermNormalizer::normalize($term);
        $column = self::COLUMNS[$type] ?? null;
        if ($column === null || !TermNormalizer::isTrackable($term) || !Uuid::isValid($productId)) {
            return;
        }

        $params = [
            'sc' => Uuid::fromHexToBytes($salesChannelId),
            'term' => $term,
            'product' => Uuid::fromHexToBytes($productId),
            'now' => $this->now(),
        ];

        // $column stammt ausschließlich aus der festen Liste oben
        $this->connection->executeStatement(
            "INSERT INTO `nova_search_term_product` (`sales_channel_id`, `term`, `product_id`, `{$column}`, `created_at`, `updated_at`)
             VALUES (:sc, :term, :product, 1, :now, :now)
             ON DUPLICATE KEY UPDATE `{$column}` = `{$column}` + 1, `updated_at` = VALUES(`updated_at`)",
            $params
        );

        $this->connection->executeStatement(
            "INSERT INTO `nova_search_term` (`sales_channel_id`, `term`, `{$column}`, `created_at`, `updated_at`)
             VALUES (:sc, :term, 1, :now, :now)
             ON DUPLICATE KEY UPDATE `{$column}` = `{$column}` + 1, `updated_at` = VALUES(`updated_at`)",
            $params
        );
    }

    /**
     * Produkte, die bei genau diesem Begriff oft geklickt, in den Warenkorb gelegt oder gekauft wurden.
     *
     * @return array<string, int> Produkt-ID (hex) => Lernwert, absteigend sortiert
     */
    public function learnedProducts(string $salesChannelId, string $term, int $minScore, int $limit = 20): array
    {
        $term = TermNormalizer::normalize($term);
        if ($term === '') {
            return [];
        }

        $score = \sprintf(
            '(`clicks` * %d + `carts` * %d + `orders` * %d)',
            self::WEIGHTS['clicks'],
            self::WEIGHTS['carts'],
            self::WEIGHTS['orders']
        );

        /** @var list<array{id: string, score: string|int}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT LOWER(HEX(`product_id`)) AS id, {$score} AS score
             FROM `nova_search_term_product`
             WHERE `sales_channel_id` = :sc AND `term` = :term AND {$score} >= :min
             ORDER BY score DESC
             LIMIT " . max(1, min($limit, 50)),
            ['sc' => Uuid::fromHexToBytes($salesChannelId), 'term' => $term, 'min' => max(1, $minScore)]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['id']] = (int) $row['score'];
        }

        return $result;
    }

    /**
     * Häufigste Suchbegriffe mit Treffern, die in den letzten 90 Tagen gesucht wurden.
     *
     * @param list<string> $blocklist
     *
     * @return list<string>
     */
    public function popularTerms(string $salesChannelId, int $minSearches, int $limit, array $blocklist = []): array
    {
        /** @var list<string> $terms */
        $terms = $this->connection->fetchFirstColumn(
            'SELECT `term` FROM `nova_search_term`
             WHERE `sales_channel_id` = :sc AND `last_hits` > 0 AND `searches` >= :min AND `updated_at` >= :since
             ORDER BY `searches` DESC, `term` ASC
             LIMIT 50',
            [
                'sc' => Uuid::fromHexToBytes($salesChannelId),
                'min' => max(1, $minSearches),
                'since' => (new \DateTimeImmutable('-90 days'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $blocklist = array_values(array_filter(array_map(TermNormalizer::normalize(...), $blocklist)));
        $terms = array_filter($terms, static function (string $term) use ($blocklist): bool {
            foreach ($blocklist as $blocked) {
                if (str_contains($term, $blocked)) {
                    return false;
                }
            }

            return true;
        });

        return \array_slice(array_values($terms), 0, $limit);
    }

    /**
     * Löscht Einträge, die seit über einem Jahr nicht mehr vorkamen (Datensparsamkeit).
     */
    public function cleanup(): void
    {
        $before = (new \DateTimeImmutable('-365 days'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->executeStatement('DELETE FROM `nova_search_term_product` WHERE `updated_at` < :before', ['before' => $before]);
        $this->connection->executeStatement('DELETE FROM `nova_search_term` WHERE `updated_at` < :before', ['before' => $before]);
    }

    /**
     * @param list<string> $productIds
     *
     * @return list<string> nur die IDs, die als Produkt existieren
     */
    public function existingProductIds(array $productIds): array
    {
        $productIds = array_values(array_filter($productIds, Uuid::isValid(...)));
        if ($productIds === []) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(`id`)) FROM `product` WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($productIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $ids;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
