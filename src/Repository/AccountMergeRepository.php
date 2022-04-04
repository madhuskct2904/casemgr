<?php

namespace App\Repository;

use Doctrine\ORM\Query;

/**
 * AccountMergeRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class AccountMergeRepository extends \Doctrine\ORM\EntityRepository
{
    public function findAll()
    {
        return $this->createQueryBuilder('am')
            ->select(['am','pa','ca'])
            ->join('am.parentAccount', 'pa')
            ->join('am.childAccount', 'ca')
            ->getQuery()
            ->getArrayResult();
    }
}