<?php declare(strict_types=1);

namespace Nova\Search\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Stündlich: offene Suchbegriffe ohne Treffer mit Claude auswerten, gespeicherte Umschreibungen
 * nachprüfen und alte Statistikdaten löschen.
 */
class SearchLearningTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'nova_search.learning';
    }

    public static function getDefaultInterval(): int
    {
        return self::HOURLY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
