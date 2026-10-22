<?php declare(strict_types=1);

namespace Nova\Search\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Nummern-Index: Artikel-, Hersteller-, EAN- und OE-Nummern ohne Leerzeichen und Trennzeichen,
 * damit „25043 25031“, „25043-25031“ und „2504325031“ dasselbe Produkt finden.
 */
class Migration1792800000NovaSearchNumberIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792800000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_search_number` (
                `product_id` BINARY(16) NOT NULL,
                `number` VARCHAR(64) NOT NULL,
                `source` VARCHAR(32) NOT NULL,
                PRIMARY KEY (`product_id`, `number`),
                KEY `idx.nova_search_number.number` (`number`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
