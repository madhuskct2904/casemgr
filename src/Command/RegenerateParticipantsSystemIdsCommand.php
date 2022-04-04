<?php

namespace App\Command;

use App\Utils\Helper;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RegenerateParticipantsSystemIdsCommand extends Command
{
    protected ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:regenerate-participants-system-ids')
            ->setDescription('Regenerate participants system IDs for account ID')
            ->addArgument('accountid', InputArgument::REQUIRED, 'Account')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $accountId = $input->getArgument('accountid');

        $conn = $this->doctrine->getConnection();

        $sql = "SELECT ud.user_id FROM users_data ud JOIN users_accounts ua ON ud.user_id = ua.users_id JOIN users u ON ud.user_id = u.id WHERE accounts_id = $accountId AND u.type ='participant'";
        $res = $conn->fetchAllAssociative($sql);

        $idsCount = count($res);
        $ids = array_column($res, 'user_id');

        do {
            $codes = [];

            for ($i = 0; $i < $idsCount;  $i++) {
                $codes[] = "'".Helper::generateCode(9)."'";
            }

            $codesStr = implode(',', $codes);

            $sql = "SELECT system_id FROM users_data WHERE system_id IN ($codesStr)";
            $res = $conn->fetchAllAssociative($sql);
        } while (count($res));

        foreach ($ids as $idx => $id) {
            $systemId = $codes[$idx];
            $sql = "UPDATE users_data SET system_id = $systemId WHERE user_id = $id";
            $conn->exec($sql);
        }
    }
}
