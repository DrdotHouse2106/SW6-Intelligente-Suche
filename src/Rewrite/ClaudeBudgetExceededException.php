<?php declare(strict_types=1);

namespace Nova\Search\Rewrite;

/**
 * Das monatliche Kostenlimit der Claude-Stufe ist erreicht – die Begriffe bleiben offen
 * und werden im nächsten Monat (oder nach Erhöhung des Limits) ausgewertet.
 */
class ClaudeBudgetExceededException extends \RuntimeException
{
}
