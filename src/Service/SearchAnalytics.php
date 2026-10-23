<?php declare(strict_types=1);

namespace Nova\Search\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Auswertung für die Administration: Kennzahlen eines Zeitraums mit Vergleich zum Vorzeitraum,
 * Tagesverlauf, Suchbegriffe, Suchen ohne Treffer und geklickte Produkte.
 *
 * Grundlage sind die Tageswerte (nova_search_term_day, nova_search_product_day) – sie werden ab
 * Version 1.6.0 gezählt; ältere Zeiträume sind daher leer.
 */
class SearchAnalytics
{
    public const PERIODS = [7, 30, 90, 365];
    private const TABLE_LIMIT = 100;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(?string $salesChannelId, int $days): array
    {
        $days = \in_array($days, self::PERIODS, true) ? $days : 30;
        $today = new \DateTimeImmutable('today');
        $from = $today->modify('-' . ($days - 1) . ' days');
        $previousFrom = $from->modify('-' . $days . ' days');
        $previousTo = $from->modify('-1 day');

        return [
            'days' => $days,
            'from' => $from->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
            'current' => $this->kpis($salesChannelId, $from, $today),
            'previous' => $this->kpis($salesChannelId, $previousFrom, $previousTo),
            'series' => $this->series($salesChannelId, $from, $today),
            'terms' => $this->terms($salesChannelId, $from, $today),
            'zeroResults' => $this->zeroResults($salesChannelId, $from, $today),
            'products' => $this->products($salesChannelId, $from, $today),
            'trackingSince' => $this->trackingSince(),
        ];
    }

    /**
     * @return array{searches: int, zeroResults: int, zeroRate: float, clicks: int, clickRate: float, carts: int, orders: int, terms: int}
     */
    private function kpis(?string $salesChannelId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        [$where, $params, $types] = $this->conditions($salesChannelId, $from, $to);
        $row = $this->connection->fetchAssociative(
            'SELECT COALESCE(SUM(`searches`), 0) AS searches, COALESCE(SUM(`zero_results`), 0) AS zero,
                    COALESCE(SUM(`clicks`), 0) AS clicks, COALESCE(SUM(`carts`), 0) AS carts,
                    COALESCE(SUM(`orders`), 0) AS orders, COUNT(DISTINCT IF(`searches` > 0, `term`, NULL)) AS terms
             FROM `nova_search_term_day` WHERE ' . $where,
            $params,
            $types
        ) ?: [];

        $searches = (int) ($row['searches'] ?? 0);
        $zero = (int) ($row['zero'] ?? 0);
        $clicks = (int) ($row['clicks'] ?? 0);

        return [
            'searches' => $searches,
            'zeroResults' => $zero,
            'zeroRate' => $searches > 0 ? round($zero / $searches * 100, 1) : 0.0,
            'clicks' => $clicks,
            'clickRate' => $searches > 0 ? round(min($clicks, $searches) / $searches * 100, 1) : 0.0,
            'carts' => (int) ($row['carts'] ?? 0),
            'orders' => (int) ($row['orders'] ?? 0),
            'terms' => (int) ($row['terms'] ?? 0),
        ];
    }

    /**
     * Ein Eintrag je Tag (auch Tage ohne Suchen).
     *
     * @return list<array{day: string, searches: int, zeroResults: int, clicks: int}>
     */
    private function series(?string $salesChannelId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        [$where, $params, $types] = $this->conditions($salesChannelId, $from, $to);
        $rows = $this->connection->fetchAllAssociativeIndexed(
            'SELECT `day`, SUM(`searches`) AS searches, SUM(`zero_results`) AS zero, SUM(`clicks`) AS clicks
             FROM `nova_search_term_day` WHERE ' . $where . ' GROUP BY `day`',
            $params,
            $types
        );

        $series = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $series[] = [
                'day' => $key,
                'searches' => (int) ($rows[$key]['searches'] ?? 0),
                'zeroResults' => (int) ($rows[$key]['zero'] ?? 0),
                'clicks' => (int) ($rows[$key]['clicks'] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function terms(?string $salesChannelId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        [$where, $params, $types] = $this->conditions($salesChannelId, $from, $to);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `term`, SUM(`searches`) AS searches, SUM(`zero_results`) AS zero, SUM(`clicks`) AS clicks,
                    SUM(`carts`) AS carts, SUM(`orders`) AS orders
             FROM `nova_search_term_day` WHERE ' . $where . '
             GROUP BY `term` HAVING searches > 0
             ORDER BY searches DESC, `term` LIMIT ' . self::TABLE_LIMIT,
            $params,
            $types
        );

        return array_map(static function (array $row): array {
            $searches = (int) $row['searches'];
            $clicks = (int) $row['clicks'];

            return [
                'term' => (string) $row['term'],
                'searches' => $searches,
                'zeroResults' => (int) $row['zero'],
                'clicks' => $clicks,
                'clickRate' => $searches > 0 ? round(min($clicks, $searches) / $searches * 100, 1) : 0.0,
                'carts' => (int) $row['carts'],
                'orders' => (int) $row['orders'],
            ];
        }, $rows);
    }

    /**
     * Begriffe, deren letzte Suche im Zeitraum ohne Treffer blieb – mit Stand der Claude-Umschreibung.
     *
     * @return list<array<string, mixed>>
     */
    private function zeroResults(?string $salesChannelId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        [$where, $params, $types] = $this->conditions($salesChannelId, $from, $to, 'd');
        $rows = $this->connection->fetchAllAssociative(
            'SELECT d.`term`, SUM(d.`zero_results`) AS zero, SUM(d.`searches`) AS searches,
                    MAX(r.`status`) AS rewrite_status, MAX(r.`rewrite`) AS rewrite
             FROM `nova_search_term_day` d
             LEFT JOIN `nova_search_rewrite` r ON r.`sales_channel_id` = d.`sales_channel_id` AND r.`term` = d.`term`
             WHERE ' . $where . '
             GROUP BY d.`term` HAVING zero > 0
             ORDER BY zero DESC, d.`term` LIMIT ' . self::TABLE_LIMIT,
            $params,
            $types
        );

        return array_map(static fn (array $row): array => [
            'term' => (string) $row['term'],
            'zeroResults' => (int) $row['zero'],
            'searches' => (int) $row['searches'],
            'rewriteStatus' => $row['rewrite_status'] !== null ? (string) $row['rewrite_status'] : null,
            'rewrite' => $row['rewrite'] !== null ? (string) $row['rewrite'] : null,
        ], $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function products(?string $salesChannelId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        [$where, $params, $types] = $this->conditions($salesChannelId, $from, $to, 'd');
        $params['live'] = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $params['language'] = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(d.`product_id`)) AS id, SUM(d.`clicks`) AS clicks, SUM(d.`carts`) AS carts, SUM(d.`orders`) AS orders,
                    MAX(p.`product_number`) AS number, MAX(COALESCE(pt.`name`, ppt.`name`)) AS name
             FROM `nova_search_product_day` d
             LEFT JOIN `product` p ON p.`id` = d.`product_id` AND p.`version_id` = :live
             LEFT JOIN `product_translation` pt ON pt.`product_id` = p.`id` AND pt.`product_version_id` = :live AND pt.`language_id` = :language
             LEFT JOIN `product_translation` ppt ON ppt.`product_id` = p.`parent_id` AND ppt.`product_version_id` = :live AND ppt.`language_id` = :language
             WHERE ' . $where . '
             GROUP BY d.`product_id`
             ORDER BY (SUM(d.`orders`) * 5 + SUM(d.`carts`) * 3 + SUM(d.`clicks`)) DESC LIMIT ' . self::TABLE_LIMIT,
            $params,
            $types
        );

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'number' => $row['number'] !== null ? (string) $row['number'] : null,
            'name' => $row['name'] !== null ? (string) $row['name'] : null,
            'clicks' => (int) $row['clicks'],
            'carts' => (int) $row['carts'],
            'orders' => (int) $row['orders'],
        ], $rows);
    }

    private function trackingSince(): ?string
    {
        $day = $this->connection->fetchOne('SELECT MIN(`day`) FROM `nova_search_term_day`');

        return \is_string($day) ? $day : null;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function conditions(?string $salesChannelId, \DateTimeImmutable $from, \DateTimeImmutable $to, string $alias = ''): array
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $where = $prefix . '`day` BETWEEN :from AND :to';
        $params = ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')];
        $types = [];

        if ($salesChannelId !== null) {
            $where .= ' AND ' . $prefix . '`sales_channel_id` = :sc';
            $params['sc'] = Uuid::fromHexToBytes($salesChannelId);
        }

        return [$where, $params, $types];
    }
}
