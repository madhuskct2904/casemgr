<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteOldActivityFeedCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('app:delete-old-activity-feed-entries')
            ->setDescription('Delete old entries from Activity Feed')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Older than X days');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $days = 30;

        if ($input->getOption('days')) {
            $days = $input->getOption('days');
        }

        $output->writeln('Deleting entries older than '.$days.' days.');

        $em = $this->doctrine->getManager();
        $em->getRepository('App:ActivityFeed')->deleteOlderThanXDays($days);

        $output->writeln('Done.');
    }
}
