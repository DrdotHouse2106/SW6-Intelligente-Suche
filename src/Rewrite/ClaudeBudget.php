<?php declare(strict_types=1);

namespace Nova\Search\Rewrite;

use Nova\Search\Service\ClaudeUsage;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Monatliches Kostenlimit der Claude-Stufe.
 *
 * Das Limit gilt global für alle Verkaufskanäle zusammen (in der Regel nutzen alle denselben
 * API-Schlüssel) und wird unter „Alle Verkaufskanäle“ eingestellt. Gezählt wird je Kalendermonat (UTC).
 * Vor jedem Aufruf wird geprüft, ob die geschätzten Höchstkosten des Aufrufs noch ins Restbudget passen.
 */
class ClaudeBudget
{
    public const DEFAULT_LIMIT_USD = 5.0;
    public const DEFAULT_WARN_PERCENT = 80;

    private const NOTICE_WARN = 'warn';
    private const NOTICE_LIMIT = 'limit';

    /** @var \Closure(): \DateTimeImmutable */
    private readonly \Closure $clock;

    /**
     * @param (\Closure(): \DateTimeImmutable)|null $clock nur für Tests
     */
    public function __construct(
        private readonly SystemConfigService $config,
        private readonly ClaudeUsage $usage,
        private readonly LoggerInterface $logger,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function month(): string
    {
        return ($this->clock)()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m');
    }

    /**
     * Limit in Mikro-USD; 0 = Claude aus.
     */
    public function limitMicro(): int
    {
        $value = $this->config->get('NovaSearch.config.claudeMonthlyLimitUsd');
        $usd = is_numeric($value) ? (float) $value : self::DEFAULT_LIMIT_USD;

        return $usd > 0 ? (int) round($usd * 1_000_000) : 0;
    }

    public function spentMicro(): int
    {
        return $this->usage->spentMicro($this->month());
    }

    public function remainingMicro(): int
    {
        return max(0, $this->limitMicro() - $this->spentMicro());
    }

    /**
     * Darf ein Aufruf mit diesen geschätzten Höchstkosten stattfinden?
     */
    public function canSpend(int $estimatedMicro = 0): bool
    {
        $limit = $this->limitMicro();
        if ($limit <= 0) {
            return false;
        }

        $spent = $this->spentMicro();
        if ($spent >= $limit || $spent + max(0, $estimatedMicro) > $limit) {
            $this->noticeOnce(self::NOTICE_LIMIT, 'warning', \sprintf(
                'Nova Suche: Monatliches Claude-Kostenlimit erreicht (%s von %s USD verbraucht). Offene Suchbegriffe werden im nächsten Monat oder nach Erhöhung des Limits ausgewertet.',
                self::usd($spent),
                self::usd($limit)
            ));

            return false;
        }

        return true;
    }

    /**
     * Verbrauch einer Antwort speichern und ggf. die Warnschwelle melden.
     *
     * @return int Kosten in Mikro-USD
     */
    public function record(string $model, int $input, int $output, int $cacheRead = 0, int $cacheWrite = 0): int
    {
        $cost = ClaudePricing::costMicro($model, $input, $output, $cacheRead, $cacheWrite);
        $month = $this->month();
        $this->usage->record($month, $model, $input, $output, $cacheRead, $cacheWrite, $cost);

        $limit = $this->limitMicro();
        $spent = $this->usage->spentMicro($month);
        $warnAt = (int) round($limit * $this->warnPercent() / 100);

        if ($limit > 0 && $spent >= $warnAt) {
            $this->noticeOnce(self::NOTICE_WARN, 'notice', \sprintf(
                'Nova Suche: %d %% des monatlichen Claude-Kostenlimits verbraucht (%s von %s USD).',
                (int) floor($spent / $limit * 100),
                self::usd($spent),
                self::usd($limit)
            ));
        }

        return $cost;
    }

    public function warnPercent(): int
    {
        $value = $this->config->get('NovaSearch.config.claudeWarnPercent');

        return is_numeric($value) ? max(1, min(100, (int) $value)) : self::DEFAULT_WARN_PERCENT;
    }

    public static function usd(int $micro): string
    {
        return number_format($micro / 1_000_000, 4, ',', '.');
    }

    private function noticeOnce(string $type, string $level, string $message): void
    {
        try {
            if ($this->usage->markNotice($this->month(), $type)) {
                $this->logger->log($level, $message);
            }
        } catch (\Throwable $e) {
            $this->logger->log($level, $message, ['exception' => $e]);
        }
    }
}
