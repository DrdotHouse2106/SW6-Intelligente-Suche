<?php declare(strict_types=1);

namespace Nova\Search\Migration;

use Doctrine\DBAL\Connection;
use Nova\Search\Rules\SearchRules;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Synonyme und Weiterleitungen als eigene Tabellen (Verwaltung unter Erweiterungen → Nova Suche).
 * Bisherige Einträge aus den Textfeldern der Plugin-Konfiguration (bis 1.6.0) werden übernommen.
 */
class Migration1793000000NovaSearchRules extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793000000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_synonym` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `terms` JSON NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.nova_search_synonym.sales_channel` (`sales_channel_id`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_redirect` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `term` VARCHAR(100) NOT NULL,
                `target` VARCHAR(2000) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.nova_search_redirect.term` (`term`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
        ');

        $this->importFromConfig($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function importFromConfig(Connection $connection): void
    {
        $rows = $connection->fetchAllAssociative(
            "SELECT `id`, `configuration_key`, `configuration_value`, `sales_channel_id` FROM `system_config`
             WHERE `configuration_key` IN ('NovaSearch.config.synonyms', 'NovaSearch.config.redirects')"
        );
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');

        foreach ($rows as $row) {
            $value = json_decode((string) $row['configuration_value'], true);
            $text = \is_array($value) && \is_string($value['_value'] ?? null) ? $value['_value'] : '';

            if ($row['configuration_key'] === 'NovaSearch.config.synonyms') {
                foreach (SearchRules::parseSynonyms($text) as $terms) {
                    $connection->insert('nova_search_synonym', [
                        'id' => random_bytes(16),
                        'sales_channel_id' => $row['sales_channel_id'],
                        'terms' => json_encode($terms, \JSON_UNESCAPED_UNICODE),
                        'active' => 1,
                        'created_at' => $now,
                    ]);
                }
            } else {
                foreach (SearchRules::parseRedirects($text) as $term => $target) {
                    $connection->insert('nova_search_redirect', [
                        'id' => random_bytes(16),
                        'sales_channel_id' => $row['sales_channel_id'],
                        'term' => $term,
                        'target' => $target,
                        'active' => 1,
                        'created_at' => $now,
                    ]);
                }
            }

            $connection->delete('system_config', ['id' => $row['id']]);
        }
    }
}
