<?php

namespace App\Repository;

use App\Entity\Accounts;
use App\Entity\Users;
use App\Enum\AccountType;
use App\Enum\ParticipantType;
use Doctrine\ORM\EntityRepository;

/**
 * Class ActivityFeedRepository
 * @package App\Repository
 */
class ActivityFeedRepository extends EntityRepository
{
    public function getIndex(Users $user, int $access = 0, Accounts $account = null, array $filters = [])
    {
        $participants = $this->participantsQuery($user, $access, $account);

        $qb = $this->createQueryBuilder('af')
            ->where('af.participant IN(:participants) OR af.account = :account')
            ->setParameter('participants', $participants)
            ->setParameter('account', $account);

        $filterFrom = $filters['from'];

        $from = $this->getMinDate($filterFrom);

        $qb->andWhere('af.createdAt >= :from')
            ->setParameter('from', $from->setTime(0, 0, 0));

        if (isset($filters['to'])) {
            $to = new \DateTime($filters['to']);
            $qb->andWhere('af.createdAt <= :to')
                ->setParameter('to', $to->setTime(23, 59, 59));
        }

        $qb->orderBy('af.createdAt', 'DESC');

        return $qb;
    }

    public function getReport(Users $user, int $access, Accounts $account, array $filters = [])
    {
        $participants = $this->participantsQuery($user, $access, $account);

        if ($account->getParticipantType() == ParticipantType::INDIVIDUAL) {
            $qb = $this->createQueryBuilder('af')
                ->select([
                    'af.template',
                    'af.title',
                    'af.createdAt',
                    'pd.first_name',
                    'pd.last_name',
                    'pd.system_id',
                    'pd.organizationId AS organization',
                    'md.first_name AS manager_first_name',
                    'md.last_name AS manager_last_name'
                ])
                ->where('af.participant IN(:participants) OR af.account = :account')
                ->setParameter('participants', $participants)
                ->setParameter('account', $account)
                ->leftJoin('af.participant', 'p')
                ->leftJoin('p.individualData', 'pd')
                ->leftJoin('App\Entity\UsersData', 'md', 'WITH', 'md.user = pd.case_manager');
        }

        if ($account->getParticipantType() == ParticipantType::MEMBER) {
            $qb = $this->createQueryBuilder('af')
                ->select([
                    'af.template',
                    'af.title',
                    'af.createdAt',
                    'pd.name AS first_name',
                    "'' AS last_name",
                    'pd.system_id',
                    'pd.organizationId AS organization',
                    'md.first_name AS manager_first_name',
                    'md.last_name AS manager_last_name'
                ])
                ->where('af.participant IN(:participants) OR af.account = :account')
                ->setParameter('participants', $participants)
                ->setParameter('account', $account)
                ->leftJoin('af.participant', 'p')
                ->leftJoin('p.memberData', 'pd')
                ->leftJoin('App\Entity\UsersData', 'md', 'WITH', 'md.user = pd.case_manager');
        }

        if (isset($filters['from'])) {
            $from = new \DateTime($filters['from']);
            $qb->andWhere('af.createdAt >= :from')
                ->setParameter('from', $from->setTime(0, 0, 0));
        }

        if (isset($filters['to'])) {
            $to = new \DateTime($filters['to']);
            $qb->andWhere('af.createdAt <= :to')
                ->setParameter('to', $to->setTime(23, 59, 59));
        }

        $qb->orderBy('af.createdAt', 'DESC');

        return $qb;
    }

    public function getWidget(Users $user, int $access = 0, Accounts $account = null)
    {
        $from = new \DateTime;
        $from->modify('-30 days');
        $from->setTime(0, 0, 0);

        $data = [];
        $participants = $this->participantsQuery($user, $access, $account);

        $qb = $this->createQueryBuilder('activity_feeds')
            ->where('activity_feeds.participant IN(:participants) OR activity_feeds.account = :account')
            ->andWhere('activity_feeds.createdAt >= :from')
            ->setParameter('participants', $participants)
            ->setParameter('account', $account)
            ->setParameter('from', $from);

        if ($access < Users::ACCESS_LEVELS['SUPERVISOR']) {
            $qb->andWhere('activity_feeds.template NOT IN (:templates)')
                ->setParameter('templates', ['referral_enrolled', 'referral_not_enrolled']);
        }

        $qb->orderBy('activity_feeds.createdAt', 'DESC')
            ->setMaxResults(20);

        foreach ($qb->getQuery()->getResult() as $row) {
            $data[] = [
                'template'    => $row->getTemplate(),
                'title'       => $row->getTitle(),
                'created'     => $row->getCreatedAt(),
                'details'     => $row->getDetails(),
                'participant' => [
                    'id'       => $row->getParticipant() ? $row->getParticipant()->getId() : null,
                    'fullName' => $row->getParticipant() ? $row->getParticipant()->getData()->getFullName(false) : null,
                    'avatar'   => $row->getParticipant() ? $row->getParticipant()->getData()->getAvatar() : null
                ],
                'template_id' => $row->getTemplateId(),
                'id'          => $row->getId()
            ];
        }

        return $data;
    }

    /*
     * volunteer / case manager - feeds associated to their participant
     * supervisor - feeds associated to their participant or other Case Managers ?!?!?!
     * PA / SA - all
     */
    private function participantsQuery(Users $user, $access, Accounts $account)
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('u.id')
            ->from('App\Entity\Users', 'u')
            ->where('u.type = :participant')
            ->setParameter('participant', 'participant')
            ->innerJoin('u.accounts', 'ua')
            ->andWhere('ua.id = :account')
            ->setParameter('account', $account->getId());

        if (!in_array($access, [
            Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'],
            Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'],
            Users::ACCESS_LEVELS['SUPERVISOR']
        ])) {

            if ($account->getParticipantType() == ParticipantType::INDIVIDUAL) {
                $qb->innerJoin('u.individualData', 'ud')
                    ->andWhere('ud.case_manager = :user')
                    ->setParameter('user', $user->getId());
            }

            if ($account->getParticipantType() == ParticipantType::MEMBER) {
                $qb->innerJoin('u.memberData', 'md')
                    ->andWhere('md.case_manager = :user')
                    ->setParameter('user', $user->getId());
            }
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param $filterFrom
     * @return \DateTime
     * @throws \Exception
     */
    protected function getMinDate($filterFrom): \DateTime
    {
        $minDate = (new \DateTime())->modify('-30 days');

        if (!$filterFrom) {
            return $minDate;
        }

        $dateFrom = (new \DateTime($filterFrom));

        if ($dateFrom->diff($minDate)->format('%r%a') >= 0) {
            return $minDate;
        }

        return $dateFrom;
    }

    public function deleteOlderThanXDays(int $days)
    {
        $date = new \DateTime();
        $date->modify('-' . $days . ' day');

        return $this->createQueryBuilder('activity_feeds')
            ->delete()
            ->andWhere('activity_feeds.createdAt < :date')
            ->setParameter(':date', $date)
            ->getQuery()
            ->execute();
    }
}
