<?php declare(strict_types=1);

namespace Nova\Search\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Merkt sich importierte Statistik-Dateien, damit dieselbe Datei nicht doppelt gezählt wird.
 */
class Migration1793100000NovaSearchImport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793100000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_import` (
                `hash` CHAR(40) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `file_name` VARCHAR(255) NOT NULL,
                `type` VARCHAR(20) NOT NULL,
                `row_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`hash`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
