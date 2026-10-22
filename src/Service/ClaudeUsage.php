<?php declare(strict_types=1);

namespace Nova\Search\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;

/**
 * Speichert den Claude-Verbrauch je Kalendermonat (UTC) und Modell.
 */
class ClaudeUsage
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function record(string $month, string $model, int $input, int $output, int $cacheRead, int $cacheWrite, int $costMicro): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->executeStatement(
            'INSERT INTO `nova_search_claude_usage`
                (`month`, `model`, `requests`, `input_tokens`, `output_tokens`, `cache_read_tokens`, `cache_write_tokens`, `cost_micro_usd`, `created_at`)
             VALUES (:month, :model, 1, :input, :output, :cacheRead, :cacheWrite, :cost, :now)
             ON DUPLICATE KEY UPDATE
                `requests` = `requests` + 1,
                `input_tokens` = `input_tokens` + VALUES(`input_tokens`),
                `output_tokens` = `output_tokens` + VALUES(`output_tokens`),
                `cache_read_tokens` = `cache_read_tokens` + VALUES(`cache_read_tokens`),
                `cache_write_tokens` = `cache_write_tokens` + VALUES(`cache_write_tokens`),
                `cost_micro_usd` = `cost_micro_usd` + VALUES(`cost_micro_usd`),
                `updated_at` = VALUES(`created_at`)',
            [
                'month' => $month,
                'model' => mb_substr($model, 0, 64),
                'input' => max(0, $input),
                'output' => max(0, $output),
                'cacheRead' => max(0, $cacheRead),
                'cacheWrite' => max(0, $cacheWrite),
                'cost' => max(0, $costMicro),
                'now' => $now,
            ]
        );
    }

    public function spentMicro(string $month): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(`cost_micro_usd`), 0) FROM `nova_search_claude_usage` WHERE `month` = :month',
            ['month' => $month]
        );
    }

    /**
     * Merkt sich, dass ein Hinweis in diesem Monat geloggt wurde.
     *
     * @return bool true, wenn der Hinweis neu ist (also jetzt geloggt werden soll)
     */
    public function markNotice(string $month, string $type): bool
    {
        return $this->connection->executeStatement(
            'INSERT IGNORE INTO `nova_search_claude_notice` (`month`, `type`, `created_at`) VALUES (:month, :type, :now)',
            ['month' => $month, 'type' => $type, 'now' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        ) > 0;
    }

    /**
     * Verbrauch je Monat und Modell, neueste Monate zuerst.
     *
     * @return list<array{month: string, model: string, requests: int, input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_write_tokens: int, cost_micro_usd: int}>
     */
    public function history(?string $sinceMonth = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `month`, `model`, `requests`, `input_tokens`, `output_tokens`, `cache_read_tokens`, `cache_write_tokens`, `cost_micro_usd`
             FROM `nova_search_claude_usage`
             ' . ($sinceMonth !== null ? 'WHERE `month` >= :since' : '') . '
             ORDER BY `month` DESC, `cost_micro_usd` DESC',
            $sinceMonth !== null ? ['since' => $sinceMonth] : []
        );

        return array_map(static fn (array $row): array => [
            'month' => (string) $row['month'],
            'model' => (string) $row['model'],
            'requests' => (int) $row['requests'],
            'input_tokens' => (int) $row['input_tokens'],
            'output_tokens' => (int) $row['output_tokens'],
            'cache_read_tokens' => (int) $row['cache_read_tokens'],
            'cache_write_tokens' => (int) $row['cache_write_tokens'],
            'cost_micro_usd' => (int) $row['cost_micro_usd'],
        ], $rows);
    }
}
