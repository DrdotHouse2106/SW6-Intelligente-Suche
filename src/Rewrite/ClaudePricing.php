<?php declare(strict_types=1);

namespace Nova\Search\Rewrite;

/**
 * Preise der Claude-Modelle in USD je 1 Mio. Tokens – für die Kostenschätzung des Kostenlimits.
 *
 * Maßgeblich ist immer die Abrechnung in der Anthropic Console; diese Tabelle ist eine
 * zweite Sicherung. Unbekannte Modelle werden mit den teuersten bekannten Preisen gerechnet.
 * Prompt-Caching nutzt das Plugin nicht; falls die API trotzdem Cache-Tokens meldet, werden
 * Cache-Lesezugriffe wie Input und Cache-Schreibzugriffe mit dem doppelten Input-Preis gerechnet
 * (bewusst eher zu hoch).
 */
final class ClaudePricing
{
    /** @var array<string, array{0: float, 1: float}> Modell => [Input, Output] */
    private const PRICES = [
        'claude-haiku-5-5' => [0.10, 0.50],
        'claude-sonnet-5-5' => [2.00, 10.00],
        'claude-opus-5-5' => [4.00, 20.00],
        'claude-fable-5-1' => [10.00, 50.00],
    ];

    /** Haiku 5.5 kostet bei Prompts über 100.000 Tokens mehr */
    private const HAIKU_LONG_CONTEXT_THRESHOLD = 100_000;
    private const HAIKU_LONG_CONTEXT = [0.50, 2.50];

    /** Teuerste bekannte Preise – für unbekannte Modelle */
    private const FALLBACK = [10.00, 50.00];

    private const CACHE_WRITE_FACTOR = 2.0;

    public static function isKnown(string $model): bool
    {
        return self::match($model) !== null;
    }

    /**
     * @return array{0: float, 1: float} [Input, Output] in USD je 1 Mio. Tokens
     */
    public static function prices(string $model, int $promptTokens): array
    {
        $key = self::match($model);
        if ($key === null) {
            return self::FALLBACK;
        }

        if ($key === 'claude-haiku-5-5' && $promptTokens > self::HAIKU_LONG_CONTEXT_THRESHOLD) {
            return self::HAIKU_LONG_CONTEXT;
        }

        return self::PRICES[$key];
    }

    /**
     * Kosten in Mikro-USD (1 USD = 1.000.000), aufgerundet.
     */
    public static function costMicro(string $model, int $input, int $output, int $cacheRead = 0, int $cacheWrite = 0): int
    {
        [$priceIn, $priceOut] = self::prices($model, $input + $cacheRead + $cacheWrite);

        // Tokens × USD je Mio. Tokens = Mikro-USD
        $micro = $input * $priceIn
            + $cacheRead * $priceIn
            + $cacheWrite * $priceIn * self::CACHE_WRITE_FACTOR
            + $output * $priceOut;

        return (int) ceil(round($micro, 6));
    }

    /**
     * Auch datierte Modell-IDs (z. B. „claude-haiku-5-5-2026…“) erkennen.
     */
    private static function match(string $model): ?string
    {
        $model = strtolower(trim($model));
        foreach (array_keys(self::PRICES) as $key) {
            if ($model === $key || str_starts_with($model, $key . '-')) {
                return $key;
            }
        }

        return null;
    }
}
