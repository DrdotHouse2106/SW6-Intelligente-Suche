<?php declare(strict_types=1);

namespace Nova\Search\Service;

/**
 * Vereinheitlicht Suchbegriffe, damit „Bremse“, „ bremse “ und „BREMSE“ zusammengezählt werden.
 */
final class TermNormalizer
{
    public const MAX_LENGTH = 100;
    private const MIN_LENGTH = 2;

    public static function normalize(string $term): string
    {
        $term = mb_strtolower(trim($term));
        $term = (string) preg_replace('/\s+/u', ' ', $term);

        return mb_substr($term, 0, self::MAX_LENGTH);
    }

    /**
     * Nur „echte“ Suchbegriffe speichern – nichts, das wie personenbezogene Daten aussieht
     * (E-Mail-Adressen, Telefonnummern) und nichts Unsinniges.
     */
    public static function isTrackable(string $normalized): bool
    {
        $length = mb_strlen($normalized);
        if ($length < self::MIN_LENGTH || $length > 60) {
            return false;
        }

        if (str_contains($normalized, '@') || str_contains($normalized, '://')) {
            return false;
        }

        // Lange Ziffernfolgen (auch mit Leerzeichen, Bindestrich, Schrägstrich) = vermutlich Telefonnummer
        if (preg_match('/\d[\d\s\/\-+]{8,}\d/', $normalized) === 1) {
            return false;
        }

        return preg_match('/\p{L}|\d/u', $normalized) === 1;
    }
}
