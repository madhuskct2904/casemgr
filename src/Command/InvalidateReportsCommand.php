<?php

namespace App\Command;

use App\Entity\ReportsForms;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InvalidateReportsCommand extends Command
{
    protected ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine) {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:invalidate-reports')
            ->setDescription('Invalidate all reports.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $reportsForms = $this->doctrine->getRepository(ReportsForms::class)->findAll();

        foreach ($reportsForms as $reportsForm) {
            $reportsForm->setInvalidatedAt(new \DateTime());
        }

        $this->doctrine->getManager()->flush();
    }
}
