<?php

namespace App\Repository;

use App\Entity\Users;

class UsersActivityLogRepository extends \Doctrine\ORM\EntityRepository
{
    public function findForUser(Users $user, ?\DateTime $from, ?\DateTime $to)
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->setParameter('user', $user);

        if ($from) {
            $qb->andWhere('l.dateTime >= :from')
                ->setParameter('from', $from->setTime(0, 0, 0));
        }

        if ($to) {
            $qb->andWhere('l.dateTime <= :to')
                ->setParameter('to', $to->setTime(23, 59, 59));
        }

        $qb->orderBy('l.dateTime', 'DESC');

        return $qb->getQuery();
    }
}
