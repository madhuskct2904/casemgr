<?php

namespace App\Repository;

use App\Entity\Accounts;
use App\Entity\Users;
use App\EntityRepository\EntityRepository;
use App\Enum\ParticipantType;
use Criteria;
use Doctrine\ORM\NonUniqueResultException;

/**
 * Class UsersRepository
 *
 * @package App\Repository
 */
class UsersRepository extends EntityRepository
{
    public function getProfileData(Users $participant = null, $fields = null, $moduleKey = 'participants_profile')
    {
        if (!is_array($fields)) {
            return [];
        }

        $data = [];
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('fv.name', 'fv.value', 'f.columns_map')
            ->from('App\Entity\FormsValues', 'fv');

        $qb->leftJoin('App\Entity\FormsData', 'fd', 'WITH', 'fd.id = fv.data');
        $qb->leftJoin('App\Entity\Forms', 'f', 'WITH', 'f.id = fd.form');
        $qb->leftJoin('App\Entity\Modules', 'm', 'WITH', 'm.id = f.module');

        $qb->where('m.key = :module');
        $qb->andWhere('fd.element_id = :element_id');

        $qb->setParameter('module', $moduleKey);
        $qb->setParameter('element_id', (string)$participant->getId());


        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $map = json_decode($row['columns_map'], true);
            $keys = (function () use ($map) {
                $keys = [];

                foreach ($map as $map_row) {
                    $keys[$map_row['value']] = $map_row['name'];
                }

                return $keys;
            })();

            if (isset($keys[$row['name']]) && in_array($keys[$row['name']], $fields)) {
                $data[$keys[$row['name']]] = $row['value'];
                continue;
            }
            // for checkboxes groupss
            $rowName = substr($row['name'], 0, strrpos($row['name'], '-'));

            if (isset($keys[$rowName]) && in_array($keys[$rowName], $fields)) {
                if (isset($data[$keys[$rowName]])) {
                    $data[$keys[$rowName]][] = $row['value'];
                    continue;
                }

                $data[$keys[$rowName]] = [$row['value']];
                continue;
            }
        }

        return $data;
    }

    /**
     * @param Users $participant
     * @param string $fieldName
     *
     * @return string|null
     * @throws NonUniqueResultException
     */
    public function getCustomFieldData(Users $participant, string $fieldName): ?string
    {
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('fv.name', 'fv.value', 'f.columns_map')
            ->from('App\Entity\FormsValues', 'fv');

        $qb->leftJoin('App\Entity\FormsData', 'fd', 'WITH', 'fd.id = fv.data');
        $qb->leftJoin('App\Entity\Forms', 'f', 'WITH', 'f.id = fd.form');
        $qb->leftJoin('App\Entity\Modules', 'm', 'WITH', 'm.id = f.module');

        $qb->where('m.key IN(:modules)');
        $qb->andWhere('fd.element_id = :element_id');
        $qb->orderBy('fv.date', Criteria::DESC);

        if ($participant->getUserDataType() == ParticipantType::INDIVIDUAL) {
            $qb->setParameter('modules', ['participants_profile','participants_assignment']);
        }

        if ($participant->getUserDataType() == ParticipantType::MEMBER) {
            $qb->setParameter('modules', ['members_profile', 'participants_assignment']);
        }

        $qb->setParameter('element_id', (string)$participant->getId());

        if (strpos($fieldName, 'checkbox-group') === 0) {
            $qb->andWhere('fv.name LIKE :field_name');
            $qb->setParameter('field_name', '%' . $fieldName . '%');
            $result = $qb->getQuery()->getResult();

            if (!$result) {
                return null;
            }

            return implode(', ', array_column($result, 'value'));
        }

        if (strpos($fieldName, 'programs-checkbox-group') === 0) {
            $qb->andWhere('fv.name LIKE :field_name');
            $qb->setParameter('field_name', '%' . $fieldName . '%');
            $qb->leftJoin('App\Entity\Programs', 'p', 'WITH', 'fv.value = p.id');
            $qb->select('DISTINCT(p.name) as program_name');
            $result = $qb->getQuery()->getResult();

            if (!$result) {
                return null;
            }

            return implode(', ', array_column($result, 'program_name'));
        }

        $qb->andWhere('fv.name = :field_name');
        $qb->setParameter('field_name', $fieldName);
        $qb->setMaxResults(1);
        $result = $qb->getQuery()->getOneOrNullResult();

        if (!$result) {
            return null;
        }

        return $result['value'];
    }

    public function findParticipantsForGroupsByIds(array $ids, $participantType = 0)
    {
        if ($participantType === ParticipantType::INDIVIDUAL) {
            return $this->createQueryBuilder('u')
                ->leftJoin('App\Entity\UsersData', 'ud', 'WITH', 'ud.user = u.id')
                ->where('u.type = :participant')
                ->setParameter('participant', 'participant')
                ->andWhere('u.id IN(:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();
        }

        if ($participantType == ParticipantType::MEMBER) {
            return $this->createQueryBuilder('u')
                ->leftJoin('App\Entity\MemberData', 'md', 'WITH', 'md.user = u.id')
                ->where('u.type = :participant')
                ->setParameter('participant', 'participant')
                ->andWhere('u.id IN(:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();
        }
    }

    public function findParticipantsForAccount(Accounts $accounts)
    {
        $query = $this->getEntityManager()->createQueryBuilder()->select('u')->from('App\Entity\Users', 'u')
            ->leftJoin('App\Entity\UsersData', 'ud', 'WITH', 'u.id = ud.user')
            ->innerJoin('u.accounts', 'ua', 'WITH', 'ua.id = :accounts')
            ->setParameter('accounts', $accounts)
            ->where('u.type = :participant')
            ->setParameter('participant', 'participant')
            ->getQuery();

        return $query->getResult();
    }

    public function findMaxUserId()
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('MAX(u.id)')
            ->from('App:Users', 'u')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByAccountAndSystemId(Accounts $accounts, string $systemId): ?Users
    {
        $user =  $this->getEntityManager()->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\Users', 'u')
            ->join('App\Entity\UsersData', 'ud', 'WITH', 'ud.user = u.id')
            ->join('u.accounts', 'ua', 'WITH', 'ua = :accounts')
            ->setParameter('accounts', $accounts)
            ->where('ud.system_id = :systemId')
            ->setParameter('systemId', $systemId)
            ->getQuery()
            ->getSingleResult();

        if ($user) {
            return $user;
        }

        return $this->getEntityManager()->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\Users', 'u')
            ->join('App\Entity\MemberData', 'md', 'WITH', 'md.user = u.id')
            ->join('u.accounts', 'ua', 'WITH', 'ua = :accounts')
            ->setParameter('accounts', $accounts)
            ->where('ud.systemId = :systemId')
            ->setParameter('systemId', $systemId)
            ->getQuery()
            ->getSingleResult();
    }
}
