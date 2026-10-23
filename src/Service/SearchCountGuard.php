<?php declare(strict_types=1);

namespace Nova\Search\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Damit die Auswertung echte Suchen zählt: Bots und automatische Abrufe werden ignoriert, und
 * derselbe Begriff zählt pro Besucher-Sitzung höchstens einmal in 30 Minuten (Neuladen,
 * Zurück-Taste, Suchfenster erneut öffnen, Suchergebnisseite nach dem Suchfenster).
 */
class SearchCountGuard
{
    private const SESSION_KEY = 'nova-search-counted';
    private const WINDOW_SECONDS = 1800;
    private const MAX_TERMS = 100;

    private const BOT_PATTERN = '/bot|crawl|spider|slurp|scrap|monitor|uptime|preview|facebookexternalhit|headless|lighthouse|pingdom|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|httpclient|guzzle/i';

    public static function isBot(Request $request): bool
    {
        $agent = (string) $request->headers->get('User-Agent', '');

        // Vorab-Laden durch den Browser („prefetch“) ist kein Aufruf durch den Besucher
        $purpose = strtolower((string) ($request->headers->get('Sec-Purpose') ?? $request->headers->get('Purpose') ?? ''));

        return $agent === '' || preg_match(self::BOT_PATTERN, $agent) === 1 || str_contains($purpose, 'prefetch');
    }

    /**
     * true, wenn die Suche gezählt werden soll – merkt sich den Begriff in der Sitzung.
     */
    public static function shouldCount(Request $request, string $term): bool
    {
        if (self::isBot($request)) {
            return false;
        }

        $term = TermNormalizer::normalize($term);
        if ($term === '' || !$request->hasSession()) {
            return $term !== '';
        }

        $session = $request->getSession();
        $now = time();
        $counted = $session->get(self::SESSION_KEY);
        $counted = \is_array($counted) ? array_filter($counted, static fn ($at): bool => \is_int($at) && $at > $now - self::WINDOW_SECONDS) : [];

        if (isset($counted[$term])) {
            return false;
        }

        $counted[$term] = $now;
        if (\count($counted) > self::MAX_TERMS) {
            $counted = \array_slice($counted, -self::MAX_TERMS, null, true);
        }
        $session->set(self::SESSION_KEY, $counted);

        return true;
    }
}
