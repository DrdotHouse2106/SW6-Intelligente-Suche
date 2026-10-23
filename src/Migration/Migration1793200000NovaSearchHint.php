<?php declare(strict_types=1);

namespace Nova\Search\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Hinweise: Kommt ein Wort im Suchbegriff vor, zeigt das Suchfenster einen Hinweis mit Link
 * (z. B. „Teile für VW finden Sie in unserem Shop …“).
 */
class Migration1793200000NovaSearchHint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793200000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_hint` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `words` JSON NOT NULL,
                `text` VARCHAR(500) NOT NULL,
                `url` VARCHAR(2000) NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.nova_search_hint.sales_channel` (`sales_channel_id`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
