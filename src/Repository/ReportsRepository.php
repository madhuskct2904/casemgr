<?php

namespace App\Repository;

use App\Entity\Accounts;
use App\EntityRepository\EntityRepository;

/**
 * Class ReportsRepository
 *
 * @package App\Repository
 */
class ReportsRepository extends EntityRepository
{
    public function findWhereIdIn($ids)
    {
        return $this->createQueryBuilder('r')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByAccount(Accounts $accounts)
    {
        return $this->createQueryBuilder('r')
            ->where('r.account = :account')
            ->setParameter('account', $accounts)
            ->getQuery()
            ->getResult();
    }
}
