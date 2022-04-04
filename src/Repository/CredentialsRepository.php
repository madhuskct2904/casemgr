<?php

namespace App\Repository;

use App\Entity\Accounts;
use App\Entity\Users;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

/**
 * Class CredentialsRepository
 * @package App\Repository
 */
class CredentialsRepository extends EntityRepository
{
    public function getCaseManagers(Accounts $account = null, $includeDisabled = false)
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('u.individualData', 'ud')
            ->where('c.access IN(:roles)')
            ->setParameter('roles', [Users::ACCESS_LEVELS['CASE_MANAGER'], Users::ACCESS_LEVELS['SUPERVISOR']]);


        if (!$includeDisabled) {
            $qb->andWhere('c.enabled = :enabled')->setParameter('enabled', 1);
        }

        if ($account !== null) {
            $qb->andWhere('c.account = :account')
                ->setParameter('account', $account->getId());
        }

        $qb->orderBy('ud.last_name', 'ASC');
        $qb->groupBy('u.id');

        $results = $qb->getQuery()->getResult();
        $data = [];

        foreach ($results as $result) {
            if ($result->getUser()->getData()) {
                $data[] = [
                    'id'          => $result->getUser()->getId(),
                    'full_name'   => $result->getUser()->getData()->getFullName(false),
                    'system_id'   => $result->getUser()->getData()->getSystemId(),
                    '$isDisabled' => !$result->isEnabled(), // $isDisabled is a special variable name for vue-multiselect
                ];
            }
        }

        return $data;
    }

    public function getCaseManager(int $managerUserId, Accounts $account)
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('u.individualData', 'ud')
            ->where('c.access IN(:roles)')
            ->andWhere('u.id = :userId')
            ->andWhere('c.account = :account')
            ->setParameter('roles', [Users::ACCESS_LEVELS['CASE_MANAGER'], Users::ACCESS_LEVELS['SUPERVISOR']])
            ->setParameter('userId', $managerUserId)
            ->setParameter('account', $account->getId());

        $data = [];

        try {
            $result = $qb->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            return $data;
        }

        if ($result->getUser()->getData()) {
            $data = [
                'id'          => $result->getUser()->getId(),
                'full_name'   => $result->getUser()->getData()->getFullName(false),
                'system_id'   => $result->getUser()->getData()->getSystemId(),
                '$isDisabled' => !$result->isEnabled(), // $isDisabled is a special variable name for vue-multiselect
            ];
        }

        return $data;
    }
}
