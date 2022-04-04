<?php

namespace App\Repository;

use App\Entity\Accounts;

class ReferralRepository extends \Doctrine\ORM\EntityRepository
{
    public function findForAccount(Accounts $account, ?\DateTime $from, ?\DateTime $to)
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.account = :account')
            ->setParameter('account', $account);

        if($from) {
            $qb->andWhere('r.createdAt >= :from')
                ->setParameter('from', $from->setTime(0, 0, 0));
        }

        if($to) {
            $qb->andWhere('r.createdAt <= :to')
                ->setParameter('to', $to->setTime(23, 59, 59));
        }

        $qb->orderBy('r.createdAt', 'DESC');

        return $qb->getQuery();
    }
}
