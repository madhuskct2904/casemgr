<?php

namespace App\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveAccountCommand extends Command
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
            ->setName('app:remove-account')
            ->setDescription('Remove account by System ID.')
            ->addArgument('systemId', InputArgument::REQUIRED, 'Account System ID');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $systemId = $input->getArgument('systemId');

        $conn = $this->doctrine->getConnection();

        $sql = "SELECT id FROM accounts WHERE system_id = '$systemId'";

        $accountId = $conn->fetchColumn($sql);

        $sql = "DELETE FROM credentials WHERE account_id = $accountId";
        $conn->exec($sql);

        $sql = "SELECT forms_id FROM forms_accounts WHERE accounts_id = $accountId";
        $res = $conn->fetchAllAssociative($sql);

        $formsIds = array_column($res, 'forms_id');
        $formsIdsStr = implode(',', $formsIds);

        if (count($formsIds)) {
            $sql = "SELECT forms_id FROM forms_accounts WHERE accounts_id != $accountId AND forms_id IN ($formsIdsStr)";
            $res = $conn->exec($sql);

            if (count($res)) {
                $formsIds2 = array_column($res, 'forms_id');
                $formsIds = array_diff($formsIds, $formsIds2);
                $formsIdsStr = implode(',', $formsIds);
            }
        }


        $sql = "SELECT id FROM forms_data WHERE account_id = $accountId";
        $res = $conn->fetchAllAssociative($sql);

        $formsDataIds = array_column($res, 'id');
        $formsDataIdsStr = implode(',', $formsDataIds);

        if (count($formsDataIds)) {
            $sql = "DELETE FROM forms_values WHERE data_id IN ($formsDataIdsStr)";
            $res = $conn->exec($sql);

            $sql = "DELETE FROM forms_data WHERE account_id = $accountId";
            $res = $conn->exec($sql);
        }


        $sql = "SELECT ud.user_id FROM users_data ud JOIN users_accounts ua ON ud.user_id = ua.users_id JOIN users u ON ud.user_id = u.id WHERE accounts_id = $accountId AND u.type ='user'";
        $res = $conn->fetchAllAssociative($sql);
        $usersIds = array_column($res, 'user_id');
        $usersIdsStr = implode(',', $usersIds);


        $sql = "SELECT users_id FROM users_accounts WHERE users_id IN ($usersIdsStr) AND accounts_id != $accountId";
        $res = $conn->fetchAllAssociative($sql);

        if (count($res)) {
            $usersIds2 = array_column($res, 'users_id');
            $usersIds = array_diff($usersIds, $usersIds2);
            $usersIdsStr = implode(',', $usersIds);
        }

        $sql = "SELECT ud.user_id FROM users_data ud JOIN users_accounts ua ON ud.user_id = ua.users_id JOIN users u ON ud.user_id = u.id WHERE accounts_id = $accountId AND u.type ='participant'";
        $res = $conn->fetchAllAssociative($sql);

        $participantsIds = array_column($res, 'user_id');
        $participantsIdsStr = implode(',', $participantsIds);

        if (count($participantsIds)) {
            $sql = "DELETE FROM messages WHERE participant_id IN ($participantsIdsStr)";
            $conn->exec($sql);

            $sql = "DELETE FROM case_notes WHERE participant_id IN ($participantsIdsStr)";
            $conn->exec($sql);

            $sql = "DELETE FROM activity_feeds WHERE participant_id IN ($participantsIdsStr)";
            $conn->exec($sql);

            $sql = "DELETE FROM users_settings WHERE user_id IN ($participantsIdsStr)";
            $conn->exec($sql);

            $sql = "DELETE FROM assignments WHERE participant_id IN ($participantsIdsStr)";
            $conn->exec($sql);
        }

        if (count($usersIds)) {
            $sql = "DELETE FROM users_settings WHERE user_id IN ($usersIdsStr)";
            $conn->exec($sql);

            $sql = "DELETE FROM activity_feeds WHERE account_id = $accountId";
            $conn->exec($sql);

            $sql = "DELETE FROM workspace_shared_files WHERE account_id = $accountId";
            $conn->exec($sql);
        }

        $sql = "DELETE FROM events WHERE account_id = $accountId";
        $conn->exec($sql);


        $sql = "DELETE FROM reports WHERE account_id = $accountId";
        $conn->exec($sql);

        if (count($formsIds)) {
            $sql = "DELETE FROM forms_history WHERE form_id IN ($formsIdsStr)";
            $conn->exec($sql);

            $sql = "DELETE FROM forms WHERE id IN ($formsIdsStr)";
            $conn->exec($sql);
        }

        if (count($participantsIds)) {
            $sql = "DELETE FROM users_data WHERE user_id IN ($participantsIdsStr)";
            $conn->exec($sql);
            $sql = "DELETE FROM users WHERE id IN ($participantsIdsStr)";
            $conn->exec($sql);
        }

        if (count($usersIds)) {
            $sql = "DELETE FROM users_data WHERE user_id IN ($participantsIdsStr)";
            $conn->exec($sql);
            $sql = "DELETE FROM users WHERE id IN ($usersIdsStr)";
            $conn->exec($sql);
        }

        $sql = "DELETE FROM accounts WHERE id = $accountId";
        $conn->exec($sql);
    }
}
