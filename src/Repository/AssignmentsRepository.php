<?php

namespace App\Repository;

use App\Entity\Assignments;
use App\Entity\Users;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;

/**
 * Class AssignmentsRepository
 * @package App\Repository
 */
class AssignmentsRepository extends EntityRepository
{
    public function getHistoryAssignments(Users $participant = null)
    {
        if ($participant === null) {
            return [];
        }

        $qb = $this->createQueryBuilder('a');

        $qb->where('a.participant = :participant')
            ->setParameter('participant', $participant)
            ->leftJoin('a.primaryCaseManager', 'apm')
            ->addSelect('apm')
            ->orderBy('a.id', 'DESC');

        $data = [];

        foreach ($qb->getQuery()->getResult() as $result) {
            $data[] = [
                'id'                     => $result->getId(),
                'programStatusStartDate' => $result->getProgramStatusStartDate(),
                'programStatusEndDate'   => $result->getProgramStatusEndDate(),
                'programStatus'          => $result->getProgramStatus(),
                'primaryCaseManager'     => $result->getPrimaryCaseManager() ? $result->getPrimaryCaseManager()->getData()->getFullName(false) : ''
            ];
        }

        return $data;
    }

    public function getCurrentAssignment(Users $participant = null)
    {
        if ($participant === null) {
            return [];
        }

        $data = [];
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('fv.name', 'fv.value', 'f.columns_map')
            ->from('App\Entity\FormsValues', 'fv')
            ->leftJoin('fv.data', 'fd')
            ->leftJoin('fd.form', 'f')
            ->leftJoin('fd.module', 'm')
            ->where('m.key = :module')
            ->andWhere('fd.element_id = :element_id')
            ->setParameter('module', 'participants_assignment')
            ->setParameter('element_id', (string)$participant->getId())
            ->andWhere('fd.assignment IS NULL')
            ->orderBy('fd.id', 'ASC');

        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $map = json_decode($row['columns_map'], true);
            $keys = (function () use ($map) {
                $keys = [];

                foreach ($map as $map_row) {
                    $keys[$map_row['value']] = $map_row['name'];
                }

                return $keys;
            })();

            if (isset($keys[$row['name']])) {
                if ($keys[$row['name']] === 'primary_case_manager_id' || $keys[$row['name']] === 'secondary_case_manager_id') {
                    $manager = $this
                        ->getEntityManager()
                        ->createQueryBuilder()
                        ->select('ud.first_name', 'ud.last_name')
                        ->from('App\Entity\UsersData', 'ud')
                        ->where('ud.user = :id')
                        ->setParameter('id', $row['value'])
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();

                    if (isset($manager['first_name'], $manager['last_name'])) {
                        $data[$keys[$row['name']]] = sprintf('%s %s', $manager['first_name'], $manager['last_name']);
                    } else {
                        $data[$keys[$row['name']]] = '';
                    }

                } else {
                    $data[$keys[$row['name']]] = $row['value'];
                }
            }
        }
        return $data;
    }

    public function findMaxProgramEndDateForParticipant($participant)
    {
        $qb = $this->createQueryBuilder('a');

        $qb->where('a.participant = :participant')
            ->setParameter('participant', $participant)
            ->orderBy('a.programStatusEndDate', 'DESC')
            ->setMaxResults(1);


        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findLatestAssignmentForParticipant($participant): ?Assignments
    {
        if ($participant === null) {
            return null;
        }

        $qb = $this->createQueryBuilder('a');

        return $qb->where('a.participant = :participant')
            ->setParameter('participant', $participant)
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
