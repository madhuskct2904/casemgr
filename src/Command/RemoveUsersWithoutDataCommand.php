<?php
namespace App\Command;

use App\Entity\Users;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class RemoveUsersWithoutDataCommand extends Command
{
    protected ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
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
            ->setName('app:remove-users-without-data')
            ->setDescription('Remove participants without data in related users_data table.')
            ->addArgument('entity', null, '')
            ->addArgument('action', null, '')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entity = in_array($input->getArgument('entity'), []) ? $input->getArgument('entity') : 'users';
        $action = in_array($input->getArgument('action'), ['fix']) ? $input->getArgument('action') : 'check';
        $em     = $this->doctrine->getManager();

        if ($entity === 'users') {
            $users      = $em->getRepository('App:Users')->findBy([
                'type' => 'participant'
            ]);
            $invalid    = 0;

            foreach ($users as $user) {
                if (!$this->userIsValid($user)) {
                    $invalid++;

                    if ($action === 'fix') {
                        $formsData = $em->getRepository('App:FormsData')->findBy([
                            'element_id' => $user->getId()
                        ]);

                        foreach ($formsData as $fd) {
                            $em->remove($fd);
                        }

                        $em->remove($user);
                        $em->flush();
                    }
                }
            }

            if ($invalid) {
                if ($action === 'fix') {
                    $output->writeln('<info>' . $invalid . ' invalid Participants removed!</info>');
                } else {
                    $output->writeln('<info>' . $invalid . ' invalid Participants found!</info>');
                }
            } else {
                $output->writeln('<info>Users OK</info>');
            }
        }
    }

    /**
     * @param Users $user
     * @return bool
     */
    private function userIsValid(Users $user)
    {
        return $user->getData() !== null;
    }
}
