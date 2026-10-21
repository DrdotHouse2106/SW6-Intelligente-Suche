<?php declare(strict_types=1);

namespace Nova\Search\Rewrite;

/**
 * Schlägt für Suchbegriffe ohne Treffer alternative Suchanfragen vor.
 */
interface RewriteSuggesterInterface
{
    public function isAvailable(string $salesChannelId): bool;

    /**
     * @param list<string> $terms normalisierte Suchbegriffe
     * @param array{shopName: string, locale: string, categories: list<string>, manufacturers: list<string>} $shop
     *
     * @return array<string, list<string>> Begriff => Alternativen (beste zuerst)
     */
    public function suggest(string $salesChannelId, array $terms, array $shop, float $timeout): array;
}
