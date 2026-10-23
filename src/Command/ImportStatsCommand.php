<?php declare(strict_types=1);

namespace Nova\Search\Command;

use Nova\Search\Service\StatsImport;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Suchbegriffe und Klicks aus dem CSV-Export eines bisherigen Suchdienstes übernehmen.
 *
 * Beispiel:
 *   bin/console nova-search:import-stats --sales-channel=<id> suchbegriffe.csv produkte.csv
 */
#[AsCommand(name: 'nova-search:import-stats', description: 'Suchbegriffe und Klicks aus CSV-Exporten eines bisherigen Suchdienstes übernehmen')]
class ImportStatsCommand extends Command
{
    public function __construct(private readonly StatsImport $import)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('files', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'CSV-Dateien (Suchbegriffe und/oder geklickte Produkte)')
            ->addOption('sales-channel', 's', InputOption::VALUE_REQUIRED, 'Verkaufskanal-ID, zu dem die Daten gehören');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $salesChannelId = strtolower((string) $input->getOption('sales-channel'));
        if (!Uuid::isValid($salesChannelId)) {
            $output->writeln('<error>Bitte --sales-channel=<ID> angeben (Einstellungen → Verkaufskanäle bzw. bin/console sales-channel:list).</error>');

            return Command::INVALID;
        }

        $status = Command::SUCCESS;
        /** @var list<string> $files */
        $files = $input->getArgument('files');
        foreach ($files as $file) {
            $text = @file_get_contents($file);
            if ($text === false) {
                $output->writeln(\sprintf('<error>%s: Datei nicht lesbar</error>', $file));
                $status = Command::FAILURE;
                continue;
            }

            try {
                $result = $this->import->import($text, $salesChannelId, basename($file));
            } catch (\InvalidArgumentException $e) {
                $output->writeln(\sprintf('<error>%s</error>', $e->getMessage()));
                $status = Command::FAILURE;
                continue;
            }

            $output->writeln($result['duplicate']
                ? \sprintf('%s: wurde bereits importiert – übersprungen', basename($file))
                : \sprintf('%s (%s): %d übernommen, %d übersprungen', basename($file), $result['type'] === StatsImport::TYPE_TERMS ? 'Suchbegriffe' : 'Produkte', $result['imported'], $result['skipped']));
        }

        return $status;
    }
}
