<?php declare(strict_types=1);

namespace Nova\Search\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Übernimmt Suchbegriffe und Klicks aus dem CSV-Export eines bisherigen Suchdienstes, damit
 * „Beliebte Suchanfragen“, das gelernte Ranking und „Beliebt in der Suche“ nicht bei null starten.
 *
 * Zwei Dateiarten werden an der Kopfzeile erkannt:
 * - Suchbegriffe: Spalten `search_term`, `total_searches`, `total_clicks`
 * - Produkte: Spalten `clicked_product_link`, `search_query`, `total_searches_clicks`
 *   (mehrere Begriffe je Zeile mit „;“ getrennt). Das Produkt wird über die Produktnummer am
 *   Ende des Links gefunden (Shopwares Standard-URL „…/Produktname/Produktnummer“).
 *
 * Die Werte werden zu vorhandenen Zählern addiert. Dieselbe Datei wird nur einmal übernommen.
 */
class StatsImport
{
    public const TYPE_TERMS = 'terms';
    public const TYPE_PRODUCTS = 'products';

    private const MAX_COUNT = 1_000_000;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{type: ?string, rows: int, imported: int, skipped: int, duplicate: bool}
     */
    public function import(string $text, string $salesChannelId, string $fileName = ''): array
    {
        if (!Uuid::isValid($salesChannelId)) {
            throw new \InvalidArgumentException('Bitte einen Verkaufskanal wählen.');
        }

        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        [$header, $rows] = self::parse($text);
        $type = self::detect($header);
        $result = ['type' => $type, 'rows' => \count($rows), 'imported' => 0, 'skipped' => 0, 'duplicate' => false];
        if ($type === null) {
            throw new \InvalidArgumentException(\sprintf('„%s“: Dateiart nicht erkannt (erwartet: Export der Suchbegriffe oder der geklickten Produkte).', $fileName ?: 'Datei'));
        }

        $hash = sha1($salesChannelId . "\n" . $text);
        if ($this->connection->fetchOne('SELECT 1 FROM `nova_search_import` WHERE `hash` = ?', [$hash]) !== false) {
            $result['duplicate'] = true;

            return $result;
        }

        $this->connection->transactional(function () use ($type, $rows, $salesChannelId, $hash, $fileName, &$result): void {
            [$imported, $skipped] = $type === self::TYPE_TERMS
                ? $this->importTerms($rows, $salesChannelId)
                : $this->importProducts($rows, $salesChannelId);
            $result['imported'] = $imported;
            $result['skipped'] = $skipped;

            $this->connection->insert('nova_search_import', [
                'hash' => $hash,
                'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                'file_name' => mb_substr($fileName, 0, 255),
                'type' => $type,
                'row_count' => \count($rows),
                'created_at' => $this->now(),
            ]);
        });

        return $result;
    }

    /**
     * @param list<string> $header
     */
    public static function detect(array $header): ?string
    {
        if (\in_array('search_term', $header, true) && \in_array('total_searches', $header, true)) {
            return self::TYPE_TERMS;
        }
        if (\in_array('clicked_product_link', $header, true) && \in_array('search_query', $header, true)) {
            return self::TYPE_PRODUCTS;
        }

        return null;
    }

    /**
     * CSV mit Komma (oder Semikolon) als Trenner; doppelte Spaltennamen behalten die erste Spalte.
     *
     * @return array{0: list<string>, 1: list<array<string, string>>}
     */
    public static function parse(string $text): array
    {
        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new \RuntimeException('Temporärer Speicher nicht verfügbar.');
        }
        fwrite($handle, $text);
        rewind($handle);

        $firstLine = strtok($text, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = [];
        $rows = [];
        while (($fields = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if ($fields === [null]) {
                continue;
            }
            $fields = array_map(static fn ($field): string => trim((string) $field), $fields);
            if ($header === []) {
                $header = array_map('strtolower', $fields);
                continue;
            }

            $row = [];
            foreach ($header as $index => $name) {
                if (!\array_key_exists($name, $row)) {
                    $row[$name] = $fields[$index] ?? '';
                }
            }
            $rows[] = $row;
        }
        fclose($handle);

        return [$header, $rows];
    }

    /**
     * Produktnummer aus „https://shop.de/Produktname/NUMMER“ (ohne Abfrage-Teil).
     */
    public static function productNumberFromLink(string $link): ?string
    {
        $path = (string) parse_url($link, \PHP_URL_PATH);
        $segment = rawurldecode(basename(rtrim($path, '/')));

        return $segment !== '' && mb_strlen($segment) <= 64 ? $segment : null;
    }

    /**
     * @param list<array<string, string>> $rows
     *
     * @return array{0: int, 1: int}
     */
    private function importTerms(array $rows, string $salesChannelId): array
    {
        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $term = TermNormalizer::normalize($row['search_term'] ?? '');
            $searches = $this->count($row['total_searches'] ?? '');
            if (!TermNormalizer::isTrackable($term) || $searches === 0) {
                ++$skipped;
                continue;
            }
            $clicks = $this->count($row['total_clicks'] ?? '');

            // Trefferzahl ist unbekannt – mit Klick gab es sicher Treffer (zählt dann für „Beliebte Suchanfragen“)
            $this->connection->executeStatement(
                'INSERT INTO `nova_search_term` (`sales_channel_id`, `term`, `searches`, `last_hits`, `clicks`, `created_at`, `updated_at`)
                 VALUES (:sc, :term, :searches, :hits, :clicks, :now, :now)
                 ON DUPLICATE KEY UPDATE `searches` = `searches` + VALUES(`searches`), `clicks` = `clicks` + VALUES(`clicks`),
                    `last_hits` = GREATEST(`last_hits`, VALUES(`last_hits`)), `updated_at` = VALUES(`updated_at`)',
                [
                    'sc' => Uuid::fromHexToBytes($salesChannelId),
                    'term' => $term,
                    'searches' => $searches,
                    'hits' => $clicks > 0 ? 1 : 0,
                    'clicks' => $clicks,
                    'now' => $this->now(),
                ]
            );
            ++$imported;
        }

        return [$imported, $skipped];
    }

    /**
     * @param list<array<string, string>> $rows
     *
     * @return array{0: int, 1: int}
     */
    private function importProducts(array $rows, string $salesChannelId): array
    {
        $numbers = [];
        foreach ($rows as $row) {
            $number = self::productNumberFromLink($row['clicked_product_link'] ?? '');
            if ($number !== null) {
                $numbers[$number] = true;
            }
        }
        $ids = $this->productIds(array_map('strval', array_keys($numbers)));

        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $number = self::productNumberFromLink($row['clicked_product_link'] ?? '');
            $productId = $number !== null ? ($ids[mb_strtolower($number)] ?? null) : null;
            if ($productId === null) {
                ++$skipped;
                continue;
            }

            $terms = explode(';', $row['search_query'] ?? '');
            $clicks = explode(';', $row['total_searches_clicks'] ?? '');
            if (\count($terms) === 1 && $clicks[0] === '') {
                $clicks = [$row['product_clicks'] ?? '1'];
            }

            foreach ($terms as $index => $term) {
                $term = TermNormalizer::normalize($term);
                $count = $this->count($clicks[$index] ?? '1');
                if (!TermNormalizer::isTrackable($term) || $count === 0) {
                    continue;
                }

                $this->connection->executeStatement(
                    'INSERT INTO `nova_search_term_product` (`sales_channel_id`, `term`, `product_id`, `clicks`, `created_at`, `updated_at`)
                     VALUES (:sc, :term, :product, :clicks, :now, :now)
                     ON DUPLICATE KEY UPDATE `clicks` = `clicks` + VALUES(`clicks`), `updated_at` = VALUES(`updated_at`)',
                    [
                        'sc' => Uuid::fromHexToBytes($salesChannelId),
                        'term' => $term,
                        'product' => $productId,
                        'clicks' => $count,
                        'now' => $this->now(),
                    ]
                );
                ++$imported;
            }
        }

        return [$imported, $skipped];
    }

    /**
     * @param list<string> $numbers
     *
     * @return array<string, string> Produktnummer (klein) => Produkt-ID (binär)
     */
    private function productIds(array $numbers): array
    {
        $ids = [];
        foreach (array_chunk($numbers, 500) as $chunk) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT `id`, `product_number` FROM `product` WHERE `version_id` = :version AND `product_number` IN (:numbers)',
                ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'numbers' => $chunk],
                ['numbers' => ArrayParameterType::STRING]
            );
            foreach ($rows as $row) {
                $ids[mb_strtolower((string) $row['product_number'])] = (string) $row['id'];
            }
        }

        return $ids;
    }

    private function count(string $value): int
    {
        return is_numeric($value) ? max(0, min(self::MAX_COUNT, (int) round((float) $value))) : 0;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
