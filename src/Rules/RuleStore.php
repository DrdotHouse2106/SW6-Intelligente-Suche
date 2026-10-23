<?php declare(strict_types=1);

namespace Nova\Search\Rules;

use Doctrine\DBAL\Connection;
use Nova\Search\Service\TermNormalizer;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Speichert Synonyme und Weiterleitungen (Verwaltung unter Erweiterungen → Nova Suche).
 *
 * Einträge ohne Verkaufskanal gelten für alle Shops. Für die Suche werden die aktiven Regeln je
 * Verkaufskanal zwischengespeichert; jede Änderung macht den Zwischenspeicher ungültig.
 */
class RuleStore
{
    private const CACHE_VERSION_KEY = 'nova_search_rules_version';
    private const MAX_TARGET_LENGTH = 2000;

    /** @var array<string, array{synonyms: list<list<string>>, redirects: array<string, string>, hints: list<array{words: list<string>, text: string, url: ?string}>}> */
    private array $memo = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Aktive Regeln für einen Verkaufskanal (inkl. der Regeln für alle Shops).
     *
     * @return array{synonyms: list<list<string>>, redirects: array<string, string>, hints: list<array{words: list<string>, text: string, url: ?string}>}
     */
    public function forSalesChannel(?string $salesChannelId): array
    {
        $key = $salesChannelId ?? 'all';
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $item = $this->cache->getItem('nova_search_rules_' . $this->version() . '_' . $key);
        if ($item->isHit() && \is_array($item->get())) {
            /** @var array{synonyms: list<list<string>>, redirects: array<string, string>, hints: list<array{words: list<string>, text: string, url: ?string}>} $rules */
            $rules = $item->get();

            return $this->memo[$key] = $rules;
        }

        [$condition, $params] = $this->salesChannelCondition($salesChannelId);

        $synonyms = [];
        foreach ($this->connection->fetchFirstColumn('SELECT `terms` FROM `nova_search_synonym` WHERE `active` = 1 AND ' . $condition, $params) as $json) {
            $terms = json_decode((string) $json, true);
            if (\is_array($terms) && \count($terms) >= 2) {
                $synonyms[] = array_values(array_map('strval', $terms));
            }
        }

        // Verkaufskanal-Regel überschreibt die allgemeine Regel mit demselben Begriff
        $redirects = [];
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `term`, `target` FROM `nova_search_redirect` WHERE `active` = 1 AND ' . $condition . ' ORDER BY (`sales_channel_id` IS NULL) DESC',
            $params
        );
        foreach ($rows as $row) {
            $redirects[(string) $row['term']] = (string) $row['target'];
        }

        // Hinweise: Kanal-Regeln zuerst
        $hints = [];
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT `words`, `text`, `url` FROM `nova_search_hint` WHERE `active` = 1 AND ' . $condition . ' ORDER BY (`sales_channel_id` IS NULL), `created_at`',
                $params
            );
        } catch (\Throwable) {
            // Tabelle fehlt (Migration noch nicht gelaufen) – Suche läuft ohne Hinweise weiter
            $rows = [];
        }
        foreach ($rows as $row) {
            $words = json_decode((string) $row['words'], true);
            if (\is_array($words) && $words !== []) {
                $hints[] = [
                    'words' => array_values(array_map('strval', $words)),
                    'text' => (string) $row['text'],
                    'url' => $row['url'] !== null && $row['url'] !== '' ? (string) $row['url'] : null,
                ];
            }
        }

        $rules = ['synonyms' => $synonyms, 'redirects' => $redirects, 'hints' => $hints];
        $item->set($rules);
        $item->expiresAfter(86400);
        $this->cache->save($item);

        return $this->memo[$key] = $rules;
    }

    /**
     * @return list<array{id: string, salesChannelId: ?string, terms: list<string>, active: bool, updatedAt: string}>
     */
    public function listSynonyms(?string $search = null): array
    {
        $params = [];
        $where = '1 = 1';
        if ($search !== null && trim($search) !== '') {
            $where = '`terms` LIKE :search';
            $params['search'] = '%' . addcslashes(TermNormalizer::normalize($search), '%_\\') . '%';
        }

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'salesChannelId' => $row['sc'] !== null ? (string) $row['sc'] : null,
            'terms' => array_values(array_map('strval', (array) json_decode((string) $row['terms'], true))),
            'active' => (bool) $row['active'],
            'updatedAt' => (string) $row['changed'],
        ], $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) AS id, LOWER(HEX(`sales_channel_id`)) AS sc, `terms`, `active`, COALESCE(`updated_at`, `created_at`) AS changed
             FROM `nova_search_synonym` WHERE ' . $where . ' ORDER BY changed DESC LIMIT 5000',
            $params
        ));
    }

    /**
     * @return list<array{id: string, salesChannelId: ?string, term: string, target: string, active: bool, updatedAt: string}>
     */
    public function listRedirects(?string $search = null): array
    {
        $params = [];
        $where = '1 = 1';
        if ($search !== null && trim($search) !== '') {
            $where = '(`term` LIKE :search OR `target` LIKE :search)';
            $params['search'] = '%' . addcslashes(mb_strtolower(trim($search)), '%_\\') . '%';
        }

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'salesChannelId' => $row['sc'] !== null ? (string) $row['sc'] : null,
            'term' => (string) $row['term'],
            'target' => (string) $row['target'],
            'active' => (bool) $row['active'],
            'updatedAt' => (string) $row['changed'],
        ], $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) AS id, LOWER(HEX(`sales_channel_id`)) AS sc, `term`, `target`, `active`, COALESCE(`updated_at`, `created_at`) AS changed
             FROM `nova_search_redirect` WHERE ' . $where . ' ORDER BY `term` LIMIT 5000',
            $params
        ));
    }

    /**
     * @return list<array{id: string, salesChannelId: ?string, words: list<string>, text: string, url: ?string, active: bool, updatedAt: string}>
     */
    public function listHints(?string $search = null): array
    {
        $params = [];
        $where = '1 = 1';
        if ($search !== null && trim($search) !== '') {
            $where = '(`words` LIKE :search OR `text` LIKE :search OR `url` LIKE :search)';
            $params['search'] = '%' . addcslashes(mb_strtolower(trim($search)), '%_\\') . '%';
        }

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'salesChannelId' => $row['sc'] !== null ? (string) $row['sc'] : null,
            'words' => array_values(array_map('strval', (array) json_decode((string) $row['words'], true))),
            'text' => (string) $row['text'],
            'url' => $row['url'] !== null ? (string) $row['url'] : null,
            'active' => (bool) $row['active'],
            'updatedAt' => (string) $row['changed'],
        ], $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) AS id, LOWER(HEX(`sales_channel_id`)) AS sc, `words`, `text`, `url`, `active`, COALESCE(`updated_at`, `created_at`) AS changed
             FROM `nova_search_hint` WHERE ' . $where . ' ORDER BY changed DESC LIMIT 1000',
            $params
        ));
    }

    /**
     * Hinweis, wenn eines der Wörter im Suchbegriff vorkommt. {term} in der Adresse wird durch den Suchbegriff ersetzt.
     *
     * @param list<string> $words
     *
     * @return string ID (hex)
     */
    public function saveHint(?string $id, ?string $salesChannelId, array $words, string $text, ?string $url, bool $active): string
    {
        $normalized = [];
        foreach ($words as $word) {
            $word = TermNormalizer::normalize((string) $word);
            if ($word !== '' && !\in_array($word, $normalized, true)) {
                $normalized[] = $word;
            }
        }
        $text = trim(strip_tags($text));
        if ($normalized === []) {
            throw new \InvalidArgumentException('Bitte mindestens ein Wort angeben.');
        }
        if ($text === '' || mb_strlen($text) > 500) {
            throw new \InvalidArgumentException('Bitte einen Hinweistext angeben (max. 500 Zeichen).');
        }
        $url = $url !== null ? trim($url) : '';
        if ($url !== '' && SearchRules::validTarget(str_replace('{term}', 'x', $url)) === null) {
            throw new \InvalidArgumentException('Der Link muss ein Pfad im Shop (z. B. /kontakt) oder eine vollständige https://-Adresse sein.');
        }

        $id = $this->upsert('nova_search_hint', $id, [
            'sales_channel_id' => $this->salesChannelBytes($salesChannelId),
            'words' => json_encode(\array_slice($normalized, 0, 50), \JSON_UNESCAPED_UNICODE),
            'text' => $text,
            'url' => $url !== '' ? $url : null,
            'active' => $active ? 1 : 0,
        ]);
        $this->invalidate();

        return $id;
    }

    /**
     * @param list<string> $terms
     *
     * @return string ID (hex)
     */
    public function saveSynonym(?string $id, ?string $salesChannelId, array $terms, bool $active): string
    {
        $normalized = [];
        foreach ($terms as $term) {
            $term = TermNormalizer::normalize((string) $term);
            if ($term !== '' && !\in_array($term, $normalized, true)) {
                $normalized[] = $term;
            }
        }
        if (\count($normalized) < 2) {
            throw new \InvalidArgumentException('Eine Synonym-Gruppe braucht mindestens zwei verschiedene Begriffe.');
        }

        $data = [
            'sales_channel_id' => $this->salesChannelBytes($salesChannelId),
            'terms' => json_encode(\array_slice($normalized, 0, SearchRules::MAX_TERMS_PER_GROUP), \JSON_UNESCAPED_UNICODE),
            'active' => $active ? 1 : 0,
        ];

        $id = $this->upsert('nova_search_synonym', $id, $data);
        $this->invalidate();

        return $id;
    }

    /**
     * @return string ID (hex)
     */
    public function saveRedirect(?string $id, ?string $salesChannelId, string $term, string $target, bool $active): string
    {
        $term = TermNormalizer::normalize($term);
        $target = SearchRules::validTarget($target);
        if ($term === '') {
            throw new \InvalidArgumentException('Bitte einen Suchbegriff angeben.');
        }
        if ($target === null || \strlen($target) > self::MAX_TARGET_LENGTH) {
            throw new \InvalidArgumentException('Das Ziel muss ein Pfad im Shop (z. B. /account) oder eine vollständige https://-Adresse sein.');
        }

        $id = $this->upsert('nova_search_redirect', $id, [
            'sales_channel_id' => $this->salesChannelBytes($salesChannelId),
            'term' => $term,
            'target' => $target,
            'active' => $active ? 1 : 0,
        ]);
        $this->invalidate();

        return $id;
    }

    public function delete(string $type, string $id): void
    {
        if (!Uuid::isValid($id)) {
            throw new \InvalidArgumentException('Ungültige ID.');
        }

        $table = match ($type) {
            'redirect' => 'nova_search_redirect',
            'hint' => 'nova_search_hint',
            default => 'nova_search_synonym',
        };
        $this->connection->delete($table, ['id' => Uuid::fromHexToBytes($id)]);
        $this->invalidate();
    }

    /**
     * Import aus CSV/Text: Synonyme eine Gruppe je Zeile, Weiterleitungen „Begriff = Ziel“ bzw. „Begriff;Ziel“.
     * Bereits vorhandene Gruppen bzw. Begriffe werden übersprungen.
     *
     * @return array{imported: int, skipped: int}
     */
    public function import(string $type, string $text, ?string $salesChannelId): array
    {
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $imported = 0;
        $skipped = 0;

        if ($type === 'redirect') {
            $existing = array_column($this->listRedirects(), 'term');
            foreach (self::parseRedirectCsv($text) as $term => $target) {
                if (\in_array($term, $existing, true)) {
                    ++$skipped;
                    continue;
                }
                $this->saveRedirect(null, $salesChannelId, $term, $target, true);
                $existing[] = $term;
                ++$imported;
            }

            return ['imported' => $imported, 'skipped' => $skipped];
        }

        $existing = array_map(static fn (array $row): string => self::groupKey($row['terms']), $this->listSynonyms());
        foreach (self::parseSynonymCsv($text) as $terms) {
            $key = self::groupKey($terms);
            // Kopfzeilen aus Exporten („name, begriffe, status“) überspringen
            if (\in_array($key, $existing, true) || \in_array('begriffe', $terms, true) || \in_array('terms', $terms, true)) {
                ++$skipped;
                continue;
            }
            $this->saveSynonym(null, $salesChannelId, $terms, true);
            $existing[] = $key;
            ++$imported;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Synonyme aus einer CSV-Datei anderer Suchdienste oder aus einfachem Text.
     * Hat eine Zeile mehrere Spalten (z. B. „Synonym;"a, b, c";aktiv“), zählt nur die Spalte mit der
     * Begriffsliste; sonst ist jede Zeile eine Gruppe mit Komma, Semikolon oder Tabulator als Trenner.
     *
     * @return list<list<string>>
     */
    public static function parseSynonymCsv(string $text): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $delimiter = str_contains($line, ';') ? ';' : (str_contains($line, "\t") ? "\t" : null);
            if ($delimiter !== null && str_contains($line, ',')) {
                $fields = str_getcsv($line, $delimiter, '"', '');
                $best = '';
                foreach ($fields as $field) {
                    if (substr_count((string) $field, ',') > substr_count($best, ',')) {
                        $best = (string) $field;
                    }
                }
                $line = $best !== '' ? $best : $line;
            }

            $lines[] = $line;
        }

        return SearchRules::parseSynonyms(implode("\n", $lines));
    }

    /**
     * Weiterleitungen aus CSV („begriff;ziel;…“, auch der eigene Export) oder Text („begriff = ziel“).
     * Erste Spalte ist der Begriff, Ziel ist die erste gültige Adresse in den übrigen Spalten –
     * Kopfzeilen fallen dadurch automatisch heraus.
     *
     * @return array<string, string> normalisierter Begriff => Ziel
     */
    public static function parseRedirectCsv(string $text): array
    {
        $redirects = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/->|=>|=/u', $line) === 1 && !str_contains($line, ';') && !str_contains($line, "\t")) {
                $redirects += SearchRules::parseRedirects($line);
                continue;
            }

            $delimiter = str_contains($line, ';') ? ';' : (str_contains($line, "\t") ? "\t" : ',');
            $fields = array_map(static fn ($field): string => trim((string) $field), str_getcsv($line, $delimiter, '"', ''));
            $term = TermNormalizer::normalize($fields[0]);
            if ($term === '' || isset($redirects[$term])) {
                continue;
            }
            foreach (\array_slice($fields, 1) as $field) {
                $target = SearchRules::validTarget($field);
                if ($target !== null) {
                    $redirects[$term] = $target;
                    break;
                }
            }
        }

        return $redirects;
    }

    /**
     * @param list<string> $terms
     */
    private static function groupKey(array $terms): string
    {
        sort($terms);

        return implode('|', $terms);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsert(string $table, ?string $id, array $data): string
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        if ($id !== null && $id !== '') {
            if (!Uuid::isValid($id)) {
                throw new \InvalidArgumentException('Ungültige ID.');
            }
            $updated = $this->connection->update($table, $data + ['updated_at' => $now], ['id' => Uuid::fromHexToBytes($id)]);
            if ($updated > 0 || $this->connection->fetchOne('SELECT 1 FROM `' . $table . '` WHERE `id` = ?', [Uuid::fromHexToBytes($id)]) !== false) {
                return $id;
            }
        }

        $newId = bin2hex(random_bytes(16));
        $this->connection->insert($table, $data + ['id' => Uuid::fromHexToBytes($newId), 'created_at' => $now]);

        return $newId;
    }

    private function salesChannelBytes(?string $salesChannelId): ?string
    {
        if ($salesChannelId === null || $salesChannelId === '') {
            return null;
        }
        if (!Uuid::isValid($salesChannelId)) {
            throw new \InvalidArgumentException('Ungültiger Verkaufskanal.');
        }

        return Uuid::fromHexToBytes($salesChannelId);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function salesChannelCondition(?string $salesChannelId): array
    {
        if ($salesChannelId === null) {
            return ['`sales_channel_id` IS NULL', []];
        }

        return ['(`sales_channel_id` IS NULL OR `sales_channel_id` = :sc)', ['sc' => Uuid::fromHexToBytes($salesChannelId)]];
    }

    private function version(): string
    {
        $item = $this->cache->getItem(self::CACHE_VERSION_KEY);

        return $item->isHit() && \is_string($item->get()) ? $item->get() : '0';
    }

    private function invalidate(): void
    {
        $this->memo = [];
        $item = $this->cache->getItem(self::CACHE_VERSION_KEY);
        $item->set(bin2hex(random_bytes(6)));
        $this->cache->save($item);
    }
}
