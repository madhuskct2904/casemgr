<?php

namespace App\Repository;

use App\Entity\Accounts;
use App\Entity\Users;
use App\Enum\ParticipantType;
use App\Enum\SystemMessageStatus;
use Doctrine\ORM\AbstractQuery;

class SystemMessageRepository extends \Doctrine\ORM\EntityRepository
{
    public function getUnreadReferralAlerts(Users $user, Accounts $account): array
    {
        $qb = $this->createQueryBuilder('sm');
        $qb->where('sm.status = :status')
            ->setParameter('status', SystemMessageStatus::UNREAD)
            ->andWhere('(sm.user = :user AND sm.account IS NULL) OR (sm.user IS NULL AND sm.account = :account)')
            ->andWhere('sm.type = :referral')
            ->setParameter('user', $user->getId())
            ->setParameter('account', $account->getId())
            ->setParameter('referral', 'referral')
            ->orderBy('sm.createdAt', 'desc');

        $messages = $qb->getQuery()->getResult();
        $data = [];

        foreach ($messages as $message) {
            $data[] = [
                'title'     => $message->getTitle(),
                'body'      => $message->getBody(),
                'createdAt' => $message->getCreatedAt()
            ];
        }

        return $data;
    }

    public function getCaseManagerAlerts(Users $user, Accounts $account): array
    {
        $qb = $this->createQueryBuilder('sm');

        $qb->where('sm.status = :status')
            ->setParameter('status', SystemMessageStatus::UNREAD)
            ->andWhere('sm.user = :user')
            ->andWhere('sm.type IN (:types)')
            ->andWhere('sm.account = :account')
            ->setParameter('user', $user->getId())
            ->setParameter('account', $account->getId())
            ->setParameter('types', ['assigned_case_manager', 'shared_form_completed', 'shared_form_failed'])
            ->orderBy('sm.createdAt', 'desc');


        $messages = $qb->getQuery()->getResult();

        $data = [];

        $avatars = [];
        $participantsIds = [];

        foreach ($messages as $message) {
            if ($message->getRelatedTo() === 'participant') {
                $participantsIds[] = $message->getRelatedToId();
            }
        }

        if (count($participantsIds)) {

            $qb = $this->getEntityManager()->createQueryBuilder('ud')->select('ud.avatar');

            if ($account->getParticipantType() == ParticipantType::INDIVIDUAL) {
                $qb->from('App:UsersData', 'ud');
            }

            if ($account->getParticipantType() == ParticipantType::MEMBER) {
                $qb->from('App:MemberData', 'ud');
            }

            $qb->where('ud.user IN (:users)')
                ->setParameter('users', $participantsIds);

            $avatars = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);

        }

        foreach ($messages as $message) {

            $data[$message->getType()][] = [
                'id'            => $message->getId(),
                'title'         => $message->getTitle(),
                'body'          => $message->getBody(),
                'participantId' => $message->getRelatedToId(),
                'createdAt'     => $message->getCreatedAt(),
                'fullName'      => $message->getTitle(),
                'avatar'        => $avatars[$message->getId()] ?? null
            ];
        }

        return $data;
    }

    public function setStatusBy(array $criteria, string $status)
    {
        if (!SystemMessageStatus::isValidValue($status)) {
            return false;
        }

        $em = $this->getEntityManager();

        $systemMessages = $this->findBy($criteria);

        if (!$systemMessages) {
            return false;
        }

        foreach ($systemMessages as $systemMessage) {
            $systemMessage->setStatus($status);
        }

        $em->flush();

        return $systemMessages;
    }

}
