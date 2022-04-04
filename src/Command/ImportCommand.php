<?php

namespace App\Command;

use App\Domain\DataImport\ImportStatus;
use App\Domain\DataImport\ImportWorker;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ImportCommand
 *
 * @package App\Command
 */
class ImportCommand extends Command
{
    protected ManagerRegistry $doctrine;
    protected ImportWorker $importWorker;

    public function __construct(
        ManagerRegistry $doctrine,
        ImportWorker $importWorker
    )
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('app:import')
            ->setDescription('handle imports')
            ->addArgument('id', InputArgument::REQUIRED, 'import entry ID - required')
            ->addArgument('offset', InputArgument::OPTIONAL, 'offset (start from, default last imported row+)', false)
            ->addArgument('limit', InputArgument::OPTIONAL, 'items to import limit (default 50)', 50)
            ->addOption('force', 'f', InputOption::VALUE_OPTIONAL, 'force import', false);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->doctrine->getManager();
        $importId = $input->getArgument('id');

        $import = $em->getRepository('App:Imports')->find($importId);

        if (!$import) {
            die('Invalid import!');
        }

        if ($input->getArgument('offset') === false) {
            $offset = $import->getLastProcessedRow() ? $import->getLastProcessedRow() + 1 : 0;
        } else {
            $offset = $input->getArgument('offset');
        }

        $limit = $input->getArgument('limit');

        if ($import->getStatus() !== ImportStatus::FINISHED || $input->getOption('force')) {
            $this->importWorker->runImport($import->getId(), $offset, $limit);
        }
    }
}
