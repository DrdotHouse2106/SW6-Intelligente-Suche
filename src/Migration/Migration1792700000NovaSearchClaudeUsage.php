<?php declare(strict_types=1);

namespace Nova\Search\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Verbrauch der Claude-Stufe je Kalendermonat (UTC) und Modell – Grundlage für das Kostenlimit.
 * Dazu eine Tabelle, die sich merkt, welche Hinweise (Warnschwelle, Limit) im Monat schon geloggt wurden.
 */
class Migration1792700000NovaSearchClaudeUsage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792700000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_claude_usage` (
                `month` CHAR(7) NOT NULL,
                `model` VARCHAR(64) NOT NULL,
                `requests` INT UNSIGNED NOT NULL DEFAULT 0,
                `input_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `output_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `cache_read_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `cache_write_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `cost_micro_usd` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`month`, `model`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_claude_notice` (
                `month` CHAR(7) NOT NULL,
                `type` VARCHAR(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`month`, `type`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
