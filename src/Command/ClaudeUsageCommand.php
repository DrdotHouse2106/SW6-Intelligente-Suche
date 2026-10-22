<?php declare(strict_types=1);

namespace Nova\Search\Command;

use Nova\Search\Rewrite\ClaudeBudget;
use Nova\Search\Service\ClaudeUsage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Zeigt Verbrauch und Kostenlimit der Claude-Stufe: aktueller Monat und die letzten 12 Monate.
 */
#[AsCommand(name: 'nova-search:claude-usage', description: 'Claude-Verbrauch und Kostenlimit anzeigen')]
class ClaudeUsageCommand extends Command
{
    public function __construct(
        private readonly ClaudeBudget $budget,
        private readonly ClaudeUsage $usage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $month = $this->budget->month();
        $limit = $this->budget->limitMicro();
        $spent = $this->budget->spentMicro();

        $since = (new \DateTimeImmutable($month . '-01', new \DateTimeZone('UTC')))->modify('-11 months')->format('Y-m');
        $history = $this->usage->history($since);

        $current = ['requests' => 0, 'input' => 0, 'output' => 0];
        $months = [];
        foreach ($history as $row) {
            if ($row['month'] === $month) {
                $current['requests'] += $row['requests'];
                $current['input'] += $row['input_tokens'] + $row['cache_read_tokens'] + $row['cache_write_tokens'];
                $current['output'] += $row['output_tokens'];
            }
            $months[$row['month']] ??= ['requests' => 0, 'cost' => 0, 'models' => []];
            $months[$row['month']]['requests'] += $row['requests'];
            $months[$row['month']]['cost'] += $row['cost_micro_usd'];
            $months[$row['month']]['models'][] = $row['model'];
        }

        $output->writeln(\sprintf('<info>Claude-Verbrauch %s (UTC)</info>', $month));
        $output->writeln(\sprintf('Anfragen:       %d', $current['requests']));
        $output->writeln(\sprintf('Tokens:         %s Input · %s Output', number_format($current['input'], 0, ',', '.'), number_format($current['output'], 0, ',', '.')));
        $output->writeln(\sprintf('Kosten:         %s USD', ClaudeBudget::usd($spent)));
        if ($limit > 0) {
            $output->writeln(\sprintf('Limit:          %s USD (Warnung ab %d %%)', ClaudeBudget::usd($limit), $this->budget->warnPercent()));
            $output->writeln(\sprintf('Rest:           %s USD', ClaudeBudget::usd(max(0, $limit - $spent))));
        } else {
            $output->writeln('Limit:          0 – Claude ist aus');
        }

        if ($months !== []) {
            $output->writeln('');
            $output->writeln('<info>Letzte 12 Monate</info>');
            $table = new Table($output);
            $table->setHeaders(['Monat', 'Anfragen', 'Kosten (USD)', 'Modelle']);
            foreach ($months as $key => $data) {
                $table->addRow([$key, $data['requests'], ClaudeBudget::usd($data['cost']), implode(', ', array_unique($data['models']))]);
            }
            $table->render();
        }

        $output->writeln('');
        $output->writeln('Hinweis: Schätzung anhand der von der API gemeldeten Tokens – maßgeblich ist die Abrechnung in der Anthropic Console.');

        return Command::SUCCESS;
    }
}
