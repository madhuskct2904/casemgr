<?php

namespace App\EntityRepository;

use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Class EntityRepository
 *
 * @package App\EntityRepository
 */
class EntityRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @param $dql
     * @param int $page
     * @param int $limit
     *
     * @return Paginator
     */
    public function paginate($dql, int $page = 1, int $limit = 20)
    {
        $paginator = new Paginator($dql);

        $paginator->getQuery()
            ->setFirstResult($limit * ($page - 1))// Offset
            ->setMaxResults($limit);// Limit

        return $paginator;
    }
}
