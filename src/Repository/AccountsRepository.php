<?php

namespace App\Repository;

use App\Entity\Users;
use Doctrine\ORM\EntityRepository;

/**
 * Class AccountsRepository
 * @package App\Repository
 */
class AccountsRepository extends EntityRepository
{
    public function findForIndex($search = null, Users $user, $access = 0)
    {
        $qb = $this->createQueryBuilder('a');

        if ($search) {
            $qb
                ->orWhere('LOWER(a.organizationName) LIKE :search')
                ->orWhere('LOWER(a.systemId) LIKE :search')
                ->orWhere('LOWER(a.accountType) LIKE :search')
                ->orWhere('LOWER(a.activationDate) LIKE :search')
                ->orWhere('LOWER(a.status) LIKE :search')
                ->setParameter('search', '%'.$search.'%')
            ;
        }

        if ($access < Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
            $credentials = $user->getCredentials()->filter(
                function ($entry) {
                    return $entry->isEnabled();
                }
            );

            $accounts = [];
            foreach ($credentials as $credential) {
                $accounts[] = $credential->getAccount()->getId();
            }

            $qb->andWhere('a.id in(:accounts)')
                ->setParameter('accounts', $accounts);
        }

        $qb->orderBy('a.organizationName', 'asc');

        return $qb;
    }

    public function findForAuth(string $url, Users $user)
    {
        return $this->createQueryBuilder('accounts')
            ->leftJoin('accounts.data', 'accounts_data')
            ->innerJoin('accounts.users', 'users', 'WITH', 'users.id = :user')
            ->innerJoin('accounts.credentials', 'credentials', 'WITH', 'credentials.user = :user')
            ->where('accounts_data.accountUrl = :url')
            ->andWhere('accounts.status = :active')
            ->andWhere('credentials.enabled = true')
            ->setParameter('url', $url)
            ->setParameter('active', 'Active')
            ->setParameter('user', $user->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findForAccountsIds($accountsArr)
    {
        return $this->createQueryBuilder('a')
            ->where('a.id IN :ids')
            ->setParameter('ids', $accountsArr)
            ->getQuery()
            ->getResult();
    }
}
