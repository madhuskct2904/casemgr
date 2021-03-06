<?php

namespace App\Repository;

/**
 * TutorialCategoryRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TutorialCategoryRepository extends \Doctrine\ORM\EntityRepository
{

    public function getMaxSort(): int
    {
        $query = $this->createQueryBuilder('c');
        $query->select('MAX(c.sort) AS max_sort');
        $result = $query->getQuery()->getSingleScalarResult();

        return $result ?: 0;
    }

}
