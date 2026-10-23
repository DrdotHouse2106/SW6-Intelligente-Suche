<?php declare(strict_types=1);

namespace Nova\Search\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Nummernsuche: Artikelnummer, Herstellernummer, EAN sowie wahlweise Zusatzfelder und Eigenschaften
 * (z. B. „OE-Nummer“) werden normalisiert gespeichert – kleingeschrieben, ohne Leerzeichen, Punkte,
 * Bindestriche und Schrägstriche. Mehrere Nummern in einem Feld (getrennt durch Komma, Semikolon,
 * Zeilenumbruch oder Leerzeichen) werden einzeln aufgenommen. Nummern mit Gruppen-Vorsatz
 * („281-281569“, „191A-194003“) sind zusätzlich über den Teil nach dem Vorsatz auffindbar („281569“).
 *
 * Der Index gilt für alle Verkaufskanäle; Sichtbarkeit und Verfügbarkeit prüft weiterhin die
 * normale Produktsuche.
 */
class NumberIndex
{
    public const SOURCES = ['productNumber', 'manufacturerNumber', 'ean'];

    private const HASH_KEY = 'NovaSearch.state.numberIndexHash';
    private const BATCH = 500;
    private const MAX_RESULTS = 200;
    private const MIN_PREFIX_LENGTH = 6;

    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $config,
    ) {
    }

    public static function normalize(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($value));
    }

    /**
     * Normalisierte Suchvarianten, wenn der Begriff wie eine Nummer aussieht (mind. 3 Ziffern,
     * keine weiteren Wörter) – sonst leer. „OE 25043 25031“ ergibt auch „2504325031“.
     *
     * @return list<string>
     */
    public static function candidates(string $term): array
    {
        $term = trim($term);
        if ($term === '' || mb_strlen($term) > 80 || preg_match('/^[\p{L}\d\s.\-\/_#:]+$/u', $term) !== 1) {
            return [];
        }

        $normalized = self::normalize($term);
        $candidates = [];
        if (self::looksLikeNumber($normalized)) {
            $candidates[] = $normalized;
        }

        // Vorangestellte Bezeichnung weglassen („oe“, „nr“, „art.-nr.“ …)
        $withoutLabel = (string) preg_replace('/^[a-z]{1,10}(?=\d)/', '', $normalized);
        if ($withoutLabel !== $normalized && preg_match('/^\d+$/', $withoutLabel) === 1 && self::looksLikeNumber($withoutLabel)) {
            $candidates[] = $withoutLabel;
        }

        return $candidates;
    }

    /**
     * Varianten für „Nur exakte Teilenummer“: jede Eingabe ab 3 Zeichen gilt als Nummer –
     * auch ohne Ziffern-Mindestzahl (z. B. „ab12cd“).
     *
     * @return list<string>
     */
    public static function exactCandidates(string $term): array
    {
        $normalized = self::normalize($term);
        if (\strlen($normalized) < 3 || \strlen($normalized) > 64) {
            return [];
        }

        $candidates = [$normalized];
        $withoutLabel = (string) preg_replace('/^(?:oe|oem|nr|art|artnr|ref|teilenr|teilenummer)(?=[a-z0-9]{3,})/', '', $normalized);
        if ($withoutLabel !== $normalized) {
            $candidates[] = $withoutLabel;
        }

        return $candidates;
    }

    /**
     * @param list<string> $candidates
     *
     * @return list<string> Produkt-IDs (hex)
     */
    public function find(array $candidates, bool $prefix): array
    {
        if ($candidates === []) {
            return [];
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(`product_id`)) FROM `nova_search_number` WHERE `number` IN (:numbers) LIMIT ' . self::MAX_RESULTS,
            ['numbers' => $candidates],
            ['numbers' => ArrayParameterType::STRING]
        );

        if ($ids === [] && $prefix) {
            foreach ($candidates as $candidate) {
                if (\strlen($candidate) < self::MIN_PREFIX_LENGTH) {
                    continue;
                }

                $ids = $this->connection->fetchFirstColumn(
                    'SELECT DISTINCT LOWER(HEX(`product_id`)) FROM `nova_search_number` WHERE `number` LIKE :prefix LIMIT ' . self::MAX_RESULTS,
                    ['prefix' => $candidate . '%']
                );
                if ($ids !== []) {
                    break;
                }
            }
        }

        return array_map('strval', $ids);
    }

    /**
     * Baut den Index neu auf – komplett (null) oder nur für die angegebenen Produkte.
     *
     * @param list<string>|null $productIds hex
     *
     * @return int Anzahl gespeicherter Nummern
     */
    public function rebuild(?array $productIds = null): int
    {
        $settings = $this->settings();

        if ($productIds === null) {
            $this->connection->executeStatement('DELETE FROM `nova_search_number`');
        } else {
            $productIds = array_values(array_filter($productIds, static fn (string $id): bool => preg_match('/^[0-9a-f]{32}$/', $id) === 1));
            if ($productIds === []) {
                return 0;
            }
            $this->connection->executeStatement(
                'DELETE FROM `nova_search_number` WHERE `product_id` IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($productIds)],
                ['ids' => ArrayParameterType::BINARY]
            );
        }

        $count = 0;
        if ($productIds !== null) {
            foreach (array_chunk($productIds, self::BATCH) as $chunk) {
                $count += $this->indexBatch(array_values(Uuid::fromHexToBytesList($chunk)), $settings);
            }
        } else {
            $last = '';
            do {
                $chunk = $this->connection->fetchFirstColumn(
                    'SELECT `id` FROM `product` WHERE `version_id` = :version AND `id` > :last ORDER BY `id` LIMIT ' . self::BATCH,
                    ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'last' => $last]
                );
                if ($chunk === []) {
                    break;
                }
                $count += $this->indexBatch(array_map('strval', $chunk), $settings);
                $last = (string) end($chunk);
            } while (\count($chunk) === self::BATCH);

            $this->config->set(self::HASH_KEY, $this->settingsHash($settings));
        }

        return $count;
    }

    /**
     * Muss der Index komplett neu aufgebaut werden (Einstellungen geändert oder noch nie gebaut)?
     */
    public function needsRebuild(): bool
    {
        if ($this->config->getString(self::HASH_KEY) !== $this->settingsHash($this->settings())) {
            return true;
        }

        return $this->connection->fetchOne('SELECT 1 FROM `nova_search_number` LIMIT 1') === false
            && $this->connection->fetchOne('SELECT 1 FROM `product` LIMIT 1') !== false;
    }

    /**
     * Zerlegt einen Feldinhalt in einzelne normalisierte Nummern.
     *
     * @return list<string>
     */
    public static function extract(string $value): array
    {
        $numbers = [];
        foreach (preg_split('/[,;|\r\n]+/u', $value) ?: [] as $piece) {
            $parts = [$piece];
            $words = preg_split('/\s+/u', trim($piece)) ?: [];
            if (\count($words) > 1) {
                array_push($parts, ...$words);
            }

            foreach ($parts as $part) {
                $number = self::normalize($part);
                if (\strlen($number) >= 3 && \strlen($number) <= 64 && preg_match('/\d/', $number) === 1) {
                    $numbers[$number] = true;
                }

                $tail = self::tail($part);
                if ($tail !== null) {
                    $numbers[$tail] = true;
                }
            }
        }

        // Rein numerische Schlüssel macht PHP zu int – zurück in Zeichenketten wandeln
        return array_map('strval', array_keys($numbers));
    }

    /**
     * Teil nach einem kurzen Gruppen-Vorsatz: „281-281569“ → „281569“, „191A-194003“ → „194003“.
     * Kunden tippen oft nur diesen Teil. Nur wenn er selbst wie eine Nummer aussieht (mind. 5 Zeichen, 3 Ziffern).
     */
    public static function tail(string $value): ?string
    {
        if (preg_match('/^\s*[a-z0-9]{1,5}\s*[-\/]\s*(\S.*)$/iu', $value, $match) !== 1) {
            return null;
        }

        $tail = self::normalize($match[1]);

        return \strlen($tail) >= 5 && \strlen($tail) <= 64 && preg_match_all('/\d/', $tail) >= 3 ? $tail : null;
    }

    private static function looksLikeNumber(string $normalized): bool
    {
        return \strlen($normalized) >= 4
            && \strlen($normalized) <= 64
            && preg_match_all('/\d/', $normalized) >= 3;
    }

    /**
     * @param list<string> $productIds binär
     * @param array{sources: list<string>, customFields: list<string>, propertyGroups: list<string>} $settings
     */
    private function indexBatch(array $productIds, array $settings): int
    {
        $live = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        /** @var array<string, array<string, string>> $rows product_id => number => source */
        $rows = [];

        $columns = array_intersect_key(
            ['productNumber' => 'product_number', 'manufacturerNumber' => 'manufacturer_number', 'ean' => 'ean'],
            array_flip($settings['sources'])
        );
        if ($columns !== []) {
            $select = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
            $products = $this->connection->fetchAllAssociative(
                'SELECT `id`, ' . $select . ' FROM `product` WHERE `version_id` = :version AND `id` IN (:ids)',
                ['version' => $live, 'ids' => $productIds],
                ['ids' => ArrayParameterType::BINARY]
            );
            foreach ($products as $product) {
                foreach ($columns as $source => $column) {
                    foreach (self::extract((string) ($product[$column] ?? '')) as $number) {
                        $rows[(string) $product['id']][$number] = $source;
                    }
                }
            }
        }

        if ($settings['customFields'] !== []) {
            $translations = $this->connection->fetchAllAssociative(
                'SELECT `product_id`, `custom_fields` FROM `product_translation`
                 WHERE `product_version_id` = :version AND `product_id` IN (:ids) AND `custom_fields` IS NOT NULL',
                ['version' => $live, 'ids' => $productIds],
                ['ids' => ArrayParameterType::BINARY]
            );
            foreach ($translations as $translation) {
                $fields = json_decode((string) $translation['custom_fields'], true);
                if (!\is_array($fields)) {
                    continue;
                }
                foreach ($settings['customFields'] as $field) {
                    $value = $fields[$field] ?? null;
                    $values = \is_array($value) ? $value : [$value];
                    foreach ($values as $single) {
                        if (\is_string($single) || \is_int($single)) {
                            foreach (self::extract((string) $single) as $number) {
                                $rows[(string) $translation['product_id']][$number] = 'customField';
                            }
                        }
                    }
                }
            }
        }

        if ($settings['propertyGroups'] !== []) {
            $options = $this->connection->fetchAllAssociative(
                'SELECT pp.`product_id`, pgot.`name`
                 FROM `product_property` pp
                 INNER JOIN `property_group_option` pgo ON pgo.`id` = pp.`property_group_option_id`
                 INNER JOIN `property_group_option_translation` pgot ON pgot.`property_group_option_id` = pgo.`id`
                 WHERE pp.`product_version_id` = :version AND pp.`product_id` IN (:ids) AND pgo.`property_group_id` IN (:groups)',
                ['version' => $live, 'ids' => $productIds, 'groups' => Uuid::fromHexToBytesList($settings['propertyGroups'])],
                ['ids' => ArrayParameterType::BINARY, 'groups' => ArrayParameterType::BINARY]
            );
            foreach ($options as $option) {
                foreach (self::extract((string) $option['name']) as $number) {
                    $rows[(string) $option['product_id']][$number] = 'property';
                }
            }
        }

        $count = 0;
        foreach (array_chunk($this->flatten($rows), 500) as $chunk) {
            $placeholders = implode(', ', array_fill(0, \count($chunk), '(?, ?, ?)'));
            $this->connection->executeStatement(
                'INSERT IGNORE INTO `nova_search_number` (`product_id`, `number`, `source`) VALUES ' . $placeholders,
                array_merge(...$chunk)
            );
            $count += \count($chunk);
        }

        return $count;
    }

    /**
     * @param array<string, array<string, string>> $rows
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function flatten(array $rows): array
    {
        $flat = [];
        foreach ($rows as $productId => $numbers) {
            foreach ($numbers as $number => $source) {
                $flat[] = [$productId, (string) $number, $source];
            }
        }

        return $flat;
    }

    /**
     * @return array{sources: list<string>, customFields: list<string>, propertyGroups: list<string>}
     */
    private function settings(): array
    {
        $sources = $this->config->get('NovaSearch.config.numberSources');
        $sources = \is_array($sources) ? array_values(array_intersect(self::SOURCES, $sources)) : [];
        // Leer = alle Nummern (abschalten über „Produkte über ihre Nummern finden“)
        $sources = $sources === [] ? self::SOURCES : $sources;

        $customFields = array_values(array_filter(
            array_map('trim', explode(',', $this->config->getString('NovaSearch.config.numberCustomFields'))),
            static fn (string $name): bool => preg_match('/^[A-Za-z][A-Za-z0-9_]{0,99}$/', $name) === 1
        ));

        $groups = $this->config->get('NovaSearch.config.numberPropertyGroups');
        $groups = \is_array($groups)
            ? array_values(array_filter($groups, static fn ($id): bool => \is_string($id) && preg_match('/^[0-9a-f]{32}$/', $id) === 1))
            : [];

        return ['sources' => $sources, 'customFields' => $customFields, 'propertyGroups' => $groups];
    }

    /**
     * @param array{sources: list<string>, customFields: list<string>, propertyGroups: list<string>} $settings
     */
    private function settingsHash(array $settings): string
    {
        // v2: zusätzlich Nummern ohne Gruppen-Vorsatz – erzwingt nach dem Update einen Neuaufbau
        return hash('sha256', (string) json_encode($settings) . '|v2');
    }
}
