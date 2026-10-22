<?php declare(strict_types=1);

namespace Nova\Search\Command;

use Nova\Search\Service\NumberIndex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'nova-search:numbers', description: 'Baut den Nummern-Index (Artikel-, Hersteller-, EAN-, OE-Nummern) neu auf')]
class NumberIndexCommand extends Command
{
    public function __construct(private readonly NumberIndex $index)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->index->rebuild();
        $output->writeln(\sprintf('Nummern-Index neu aufgebaut: %d Nummern.', $count));

        return self::SUCCESS;
    }
}
