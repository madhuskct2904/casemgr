<?php

namespace App\Repository;

use App\Entity\Accounts;
use App\Enum\ParticipantType;
use Doctrine\ORM\EntityRepository;

/**
 * Class ImportsRepository
 * @package App\Repository
 */
class ImportsRepository extends EntityRepository
{
    /**
     * @param $systemId
     * @param Accounts $account
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findParticipantBySystemId($systemId, Accounts $account)
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\Users', 'u')
            ->where('u.type = :participant')
            ->setParameter('participant', 'participant')
            ->innerJoin('u.accounts', 'ua')
            ->andWhere('ua.id = :account')
            ->setParameter('account', $account->getId());

        if ($account->getParticipantType() == ParticipantType::MEMBER) {
            $qb = $qb->innerJoin('u.memberData', 'ud')
                ->andWhere('ud.systemId = :system_id')
                ->setParameter('system_id', $systemId);
        }

        if ($account->getParticipantType() == ParticipantType::INDIVIDUAL) {
            $qb = $qb->innerJoin('u.individualData', 'ud')
                ->andWhere('ud.system_id = :system_id')
                ->setParameter('system_id', $systemId);
        }

        return $qb->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param Accounts $account
     * @return mixed
     */
    public function findHistory(Accounts $account)
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.form', 'if')
            ->where('i.formAccount = :account OR i.account = :account')
            ->setParameter('account', $account)
            ->orderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Accounts $account
     * @return mixed
     */
    public function findExpired(Accounts $account)
    {
        $date = new \DateTime();
        $date->modify('-14 days');

        return $this->createQueryBuilder('i')
            ->leftJoin('i.form', 'if')
            ->where('i.formAccount = :account OR i.account = :account')
            ->setParameter('account', $account->getId())
            ->orderBy('i.id', 'DESC')
            ->andWhere('i.createdDate < :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->andWhere('i.status NOT IN(:statuses)')
            ->setParameter('statuses', ['running'])
            ->getQuery()
            ->getResult();
    }
}
