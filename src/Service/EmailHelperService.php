<?php namespace App\Service;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;

class EmailHelperService
{
    protected $em;
    protected $senders;

    public function __construct(EntityManagerInterface $em, array $emailSenders)
    {
        $this->em = $em;
        $this->emailSenders = $emailSenders;
    }

    public function getNewEmailOptions()
    {
        $recipientsGroups = [
            'all_users'        => 'All Users',
            'users_by_account' => 'Users by Account',
            'users_by_role'    => 'Users by User Role',
            'custom'           => 'Custom Recipient'
        ];

        $allUsers = $this->em->getRepository('App:Users')->findBy(['type' => 'user', 'enabled' => 1]);

        $usersByRole = [];
        $usersByAccount = [];
        $accounts = [];

        foreach ($allUsers as $user) {
            $credentials = $user->getCredentials();

            foreach ($credentials as $credential) {
                if (!$credential->isEnabled()) {
                    continue;
                }

                switch ($credential->getAccess()) {
                    case(Users::ACCESS_LEVELS['VOLUNTEER']):
                        $key = 'volunteers';
                        break;
                    case(Users::ACCESS_LEVELS['CASE_MANAGER']):
                        $key = 'case_managers';
                        break;
                    case(Users::ACCESS_LEVELS['SUPERVISOR']):
                        $key = 'supervisors';
                        break;
                    case(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR']):
                        $key = 'program_administrators';
                        break;
                    case(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']):
                        $key = 'system_administrators';
                        break;
                }

                if (isset($key)) {
                    $usersByRole[$key][$user->getId()] = [
                        'email' => $user->getEmailCanonical()
                    ];
                }

                // skip system administrators in 'by_account' administrators
                if ($credential->getAccess() == Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
                    continue;
                }

                $usersByRole['all_users'][$user->getId()] = [
                    'email' => $user->getEmailCanonical()
                ];

                $userAccount = $credential->getAccount();

                if (!isset($accounts['account' . $userAccount->getId()])) {
                    $accounts['account' . $userAccount->getId()] = [
                        'id'   => $userAccount->getId(),
                        'name' => $userAccount->getOrganizationName()
                    ];
                }

                $usersByAccount['account' . $userAccount->getId()][$user->getId()] = [
                    'email' => $user->getEmailCanonical()
                ];
            }
        }

        $accounts = array_values($accounts);

        $usersByRole = array_map(function ($item) {
            return array_values($item);
        }, $usersByRole);

        $usersByAccount = array_map(function ($item) {
            return array_values($item);
        }, $usersByAccount);

        return [
            'senders'           => $this->emailSenders,
            'accounts'          => $accounts,
            'recipients_groups' => $recipientsGroups,
            'users_by_role'     => $usersByRole,
            'users_by_account'  => $usersByAccount
        ];
    }
}
