<?php declare(strict_types=1);

namespace Nova\Search\Rules;

use Nova\Search\Service\TermNormalizer;

/**
 * Wendet Synonyme und Weiterleitungen an (gepflegt unter Erweiterungen → Nova Suche, gespeichert im
 * RuleStore). Regeln ohne Verkaufskanal gelten für alle Shops.
 *
 * Die statischen parse…-Methoden lesen das Textformat für Import und Übernahme:
 *  - Synonyme: eine Gruppe je Zeile, Begriffe mit Komma getrennt („bremsklötze, bremsbeläge“)
 *  - Weiterleitungen: ein Begriff je Zeile, Ziel nach „=“, „->“ oder „;“ („kundenkonto = /account“)
 * Leere Zeilen und Zeilen mit „#“ am Anfang werden ignoriert.
 */
class SearchRules
{
    public const MAX_GROUPS = 1000;
    public const MAX_TERMS_PER_GROUP = 20;
    public const MAX_REDIRECTS = 1000;

    public function __construct(private readonly RuleStore $store)
    {
    }

    /**
     * @return list<list<string>>
     */
    public function synonymGroups(?string $salesChannelId): array
    {
        return $this->store->forSalesChannel($salesChannelId)['synonyms'];
    }

    /**
     * Synonyme, die im Suchbegriff als ganze Wörter vorkommen.
     *
     * @return list<array{phrase: string, alternatives: list<string>}>
     */
    public function expansions(string $term, ?string $salesChannelId): array
    {
        $normalized = ' ' . TermNormalizer::normalize($term) . ' ';
        if (trim($normalized) === '') {
            return [];
        }

        $result = [];
        foreach ($this->synonymGroups($salesChannelId) as $group) {
            foreach ($group as $phrase) {
                if (!str_contains($normalized, ' ' . $phrase . ' ')) {
                    continue;
                }

                $alternatives = array_values(array_filter($group, static fn (string $other): bool => $other !== $phrase));
                $result[] = ['phrase' => $phrase, 'alternatives' => $alternatives];

                // Je Gruppe nur der erste Treffer, sonst werden dieselben Begriffe mehrfach ergänzt
                break;
            }
        }

        return $result;
    }

    public function redirect(string $term, ?string $salesChannelId): ?string
    {
        return $this->store->forSalesChannel($salesChannelId)['redirects'][TermNormalizer::normalize($term)] ?? null;
    }

    /**
     * Hinweise, deren Wörter im Suchbegriff als ganze Wörter vorkommen (z. B. „t2“ in „spiegel t2“).
     * {term} im Link wird durch den Suchbegriff ersetzt.
     *
     * @return list<array{text: string, url: ?string}>
     */
    public function hints(string $term, ?string $salesChannelId, int $limit = 2): array
    {
        $normalized = TermNormalizer::normalize($term);
        if ($normalized === '') {
            return [];
        }
        $padded = ' ' . $normalized . ' ';

        $result = [];
        foreach ($this->store->forSalesChannel($salesChannelId)['hints'] as $hint) {
            foreach ($hint['words'] as $word) {
                if (str_contains($padded, ' ' . $word . ' ')) {
                    $result[] = [
                        'text' => $hint['text'],
                        'url' => $hint['url'] !== null ? str_replace('{term}', rawurlencode($normalized), $hint['url']) : null,
                    ];
                    break;
                }
            }
            if (\count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * @return list<list<string>>
     */
    public static function parseSynonyms(string $raw): array
    {
        $groups = [];
        foreach (self::lines($raw) as $line) {
            $terms = [];
            foreach (preg_split('/[,;\t]/u', $line) ?: [] as $term) {
                $term = TermNormalizer::normalize($term);
                if ($term !== '' && !\in_array($term, $terms, true)) {
                    $terms[] = $term;
                }
            }

            if (\count($terms) < 2) {
                continue;
            }

            $groups[] = \array_slice($terms, 0, self::MAX_TERMS_PER_GROUP);
        }

        return \array_slice($groups, 0, self::MAX_GROUPS);
    }

    /**
     * @return array<string, string> normalisierter Begriff => Ziel
     */
    public static function parseRedirects(string $raw): array
    {
        $redirects = [];
        foreach (self::lines($raw) as $line) {
            $parts = preg_split('/\s*(?:->|=>|=|;|\t)\s*/u', $line, 2);
            if ($parts === false || \count($parts) !== 2) {
                continue;
            }

            $term = TermNormalizer::normalize($parts[0]);
            $target = self::validTarget($parts[1]);
            if ($term === '' || $target === null) {
                continue;
            }

            $redirects[$term] = $target;
        }

        return \array_slice($redirects, 0, self::MAX_REDIRECTS, true);
    }

    /**
     * Erlaubt sind Pfade im Shop („/account“) und vollständige http(s)-Adressen.
     */
    public static function validTarget(string $target): ?string
    {
        $target = trim($target);
        if ($target === '' || \strlen($target) > 2000 || preg_match('/[\x00-\x20\x7F"\'<>\\\\]/', $target) === 1) {
            return null;
        }

        if (str_starts_with($target, '/') && !str_starts_with($target, '//')) {
            return $target;
        }

        $scheme = strtolower((string) parse_url($target, \PHP_URL_SCHEME));
        $host = parse_url($target, \PHP_URL_HOST);

        return \in_array($scheme, ['http', 'https'], true) && \is_string($host) && $host !== '' ? $target : null;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $raw): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '#')) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
