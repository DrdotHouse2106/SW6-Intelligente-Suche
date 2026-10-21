<?php declare(strict_types=1);

namespace Nova\Search\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Tabellen für das Lernen aus dem Suchverhalten und für gespeicherte Umschreibungen.
 * Es werden keine personenbezogenen Daten gespeichert – nur Suchbegriffe, Produkt-IDs und Zähler.
 */
class Migration1792600000NovaSearchLearning extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792600000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_term` (
                `sales_channel_id` BINARY(16) NOT NULL,
                `term` VARCHAR(100) NOT NULL,
                `searches` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
                `carts` INT UNSIGNED NOT NULL DEFAULT 0,
                `orders` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`sales_channel_id`, `term`),
                KEY `idx.nova_search_term.popular` (`sales_channel_id`, `updated_at`, `searches`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_term_product` (
                `sales_channel_id` BINARY(16) NOT NULL,
                `term` VARCHAR(100) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
                `carts` INT UNSIGNED NOT NULL DEFAULT 0,
                `orders` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`sales_channel_id`, `term`, `product_id`),
                KEY `idx.nova_search_term_product.updated` (`updated_at`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_rewrite` (
                `sales_channel_id` BINARY(16) NOT NULL,
                `term` VARCHAR(100) NOT NULL,
                `rewrite` VARCHAR(100) NULL,
                `status` VARCHAR(16) NOT NULL,
                `hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`sales_channel_id`, `term`),
                KEY `idx.nova_search_rewrite.status` (`status`, `updated_at`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
