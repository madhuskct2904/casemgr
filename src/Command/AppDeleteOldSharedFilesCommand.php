<?php

namespace App\Command;

use App\Service\S3ClientFactory;
use Aws\S3\Exception\S3Exception;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AppDeleteOldSharedFilesCommand extends Command
{
    protected ManagerRegistry $doctrine;
    protected $s3Client;
    protected ContainerInterface $container;

    public function __construct(
        ManagerRegistry $doctrine,
        S3ClientFactory $s3ClientFactory,
        ContainerInterface $container
    )
    {
        $this->doctrine = $doctrine;
        $this->s3Client = $s3ClientFactory->getClient();
        $this->container = $container;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:delete-old-shared-files')
            ->setDescription('Delete old workspace shared files.')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Older than X days')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $days = 30;

        if ($input->getOption('days')) {
            $days = $input->getOption('days');
        }

        $em = $this->doctrine->getManager();

        $workspaceSharedFiles = $em->getRepository('App:WorkspaceSharedFile')->findDeletedMoreThanXDaysAgo($days);

        if (!count($workspaceSharedFiles)) {
            $output->writeln('There is no old files to delete.');
            return;
        }

        foreach ($workspaceSharedFiles as $workspaceSharedFile) {
            $output->write('Trying to delete: '.$workspaceSharedFile->getOriginalFilename().'...');

            $bucket = $this->container->getParameter('aws_bucket_name');
            $prefix = $this->container->getParameter('aws_workspace_shared_files_folder');

            try {
                $this->s3Client->deleteObject([
                    'Bucket'     => $bucket,
                    'Key'        => $prefix . '/' . $workspaceSharedFile->getServerFilename()
                ]);
            } catch (S3Exception $e) {
                $output->writeln('error: '.$e->getMessage());
            }

            $output->writeln('done.');
            $em->remove($workspaceSharedFile);
        }

        $em->flush();
        $output->writeln('Done');
    }
}
