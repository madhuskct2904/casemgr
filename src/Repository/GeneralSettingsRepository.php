<?php

namespace App\Repository;

use App\EntityRepository\EntityRepository;
use Doctrine\ORM\Query;

/**
 * Class GeneralSettingsRepository
 *
 * @package App\Repository
 */
class GeneralSettingsRepository extends EntityRepository
{
    /**
     * @param array $keys
     *
     * @param int $output
     *
     * @return mixed
     */
    public function findByKeys(array $keys, int $output = Query::HYDRATE_OBJECT)
    {
        return $this->createQueryBuilder('gs')
                    ->where('gs.key IN (:keys)')
                    ->setParameter('keys', $keys)
                    ->getQuery()
                    ->getResult($output);
    }
}
