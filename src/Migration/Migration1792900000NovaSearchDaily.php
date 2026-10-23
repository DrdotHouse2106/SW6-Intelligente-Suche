<?php declare(strict_types=1);

namespace Nova\Search\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Tageswerte für die Auswertung in der Administration (Zeiträume, Vergleich, Verlauf):
 * je Tag und Suchbegriff sowie je Tag und Produkt. Weiterhin anonym, ohne Personenbezug.
 */
class Migration1792900000NovaSearchDaily extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792900000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_term_day` (
                `sales_channel_id` BINARY(16) NOT NULL,
                `day` DATE NOT NULL,
                `term` VARCHAR(100) NOT NULL,
                `searches` INT UNSIGNED NOT NULL DEFAULT 0,
                `zero_results` INT UNSIGNED NOT NULL DEFAULT 0,
                `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
                `carts` INT UNSIGNED NOT NULL DEFAULT 0,
                `orders` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`sales_channel_id`, `day`, `term`),
                KEY `idx.nova_search_term_day.day` (`day`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_product_day` (
                `sales_channel_id` BINARY(16) NOT NULL,
                `day` DATE NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
                `carts` INT UNSIGNED NOT NULL DEFAULT 0,
                `orders` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`sales_channel_id`, `day`, `product_id`),
                KEY `idx.nova_search_product_day.day` (`day`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
