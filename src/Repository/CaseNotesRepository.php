<?php

namespace App\Repository;

use App\Entity\Users;
use Doctrine\ORM\EntityRepository;

/**
 * Class CaseNotesRepository
 * @package App\Repository
 */
class CaseNotesRepository extends EntityRepository
{
    public function getWidget(Users $participant)
    {
        $data = [];
        $qb = $this->createQueryBuilder('cn');

        $qb
            ->where('cn.participant = :participant')
            ->setParameter('participant', $participant->getId())
            ->andWhere('cn.assignment IS NULL')
            ->orderBy('cn.createdAt', 'DESC')
            ->setMaxResults(20);

        foreach ($qb->getQuery()->getResult() as $row) {
            $data[] = [
                'type'      => $row->getType(),
                'note'      => $row->getNote(),
                'createdAt' => $row->getCreatedAt(),
                'updatedAt' => $row->getModifiedAt(),
                'createdBy' => $row->getCreatedBy()
                    ? ['fullName' => $row->getCreatedBy()->getData()->getFullName(false)]
                    : ['fullName' => 'System Administrator'],
                'updatedBy' => $row->getModifiedBy() ? [
                    'fullName' => $row->getModifiedBy()->getData()->getFullName(false)
                ] : null,
                'manager'   => $row->getManager() ? [
                    'fullName' => $row->getManager()->getData()->getFullName(false)
                ] : null,
                'id'        => $row->getId(),
                'read_only' => $row->isReadOnly()
            ];
        }

        return $data;
    }

    public function findByParticipantIdAndCurrentAssignment(int $participantId, ?string $search)
    {
        $qb = $this->createQueryBuilder('cn')
            ->where('cn.assignment IS NULL')
            ->andWhere('cn.participant = :id')
            ->setParameter('id', $participantId);

        if ($search && $search !== '') {
            $qb->andWhere('cn.note LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }


        return $qb->getQuery();
    }
}
