<?php

namespace App\Repository;

use App\Entity\Accounts;
// use App\Entity\CaseNotes;
use App\Entity\Events;
use App\Entity\Users;
// use App\Event\ActivityFeedEvent;
use Doctrine\ORM\EntityRepository;

/**
 * Class EventsRepository
 *
 * @package App\Repository
 */
class EventsRepository extends EntityRepository
{
    /**
     * @param Users $user
     * @param Accounts $account
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function extract(Users $user, Accounts $account)
    {
        $qb = $this->createQueryBuilder('e');

        return $qb
            ->andWhere('e.createdBy = :userId')
            ->andWhere('e.account = :accountId')
            ->setParameter('userId', $user->getId())
            ->setParameter('accountId', $account->getId());
    }

    /**
     * @param Events $event
     * @return bool
     */
    public function remove(Events $event)
    {
        try {
            $em = $this->getEntityManager();

            if ($event->getCaseNote()) {
                $em->remove($event->getCaseNote());
            }

            $em->remove($event);
            $em->flush();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param Events|null $event
     * @param Users $user
     * @param Accounts $account
     * @param $request
     * @return Events
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function save(Events $event = null, Users $user, Accounts $account, $request): Events
    {
        $em = $this->getEntityManager();

        $format     = 'm/d/Y h:i A';
        $timezone   = $user->getData()->getTimeZone();
        if ($timezone and $timezone === 'Etc/GMT-12') {
            $format = 'Y/m/d h:i A';
        }

        $participant = null;
        preg_match('/data-id="(\d+)"/', $request->param('title'), $matches);
        if (isset($matches[1])) {
            $participant = $this->getEntityManager()->getRepository('App:Users')->find($matches[1]);
        }

        if ($new = ! $event instanceof Events) {
            $event = new Events();
            $event->setCreatedBy($user);
            $event->setAccount($account);
            $event->setCreatedAt(new \DateTime());
        }

        $event->setTitle($request->param('title'));

        /* if ($participant AND ($participant != $event->getParticipant())) {

            $caseNote = new CaseNotes();
            $caseNote->setNote(strip_tags($request->param('title')));
            $caseNote->setType('Event');
            $caseNote->setCreatedBy($user);
            $caseNote->setManager($user);
            $caseNote->setParticipant($participant);
            $em->persist($caseNote);

            $event->setCaseNote($caseNote);
        } */

        $event->setParticipant($participant);

        $event->setModifiedBy($user);
        $event->setModifiedAt(new \DateTime());

        $event->setAllDay($request->param('all_day'));
        $event->setComment($request->param('comment'));

        $datetime = new \DateTime();

        $startDate = $datetime->createFromFormat($format, $request->param('start_date_time'));
        if ($request->param('all_day')) {
            $startDate->setTime(0, 0, 0);
        }
        $event->setStartDateTime($startDate);

        $endDate = $datetime->createFromFormat($format, $request->param('end_date_time'));
        if ($request->param('all_day')) {
            $endDate->setTime(23, 59, 59);
        }
        $event->setEndDateTime($endDate);

        $em->persist($event);
        $em->flush();

        return $event;
    }

    /**
     * @param null $dateStart
     * @param null $dateEnd
     * @param Users $user
     * @param Accounts $account
     * @return \Doctrine\ORM\Query
     */
    public function getAllByDates($dateStart = null, $dateEnd = null, Users $user, Accounts $account)
    {
        $qb = $this->extract($user, $account);

        $format     = 'm/d/Y';
        $timezone   = $user->getData()->getTimeZone();
        if ($timezone and $timezone === 'Etc/GMT-12') {
            $format = 'Y/m/d';
        }

        $datetime = new \DateTime();
        $startDate = $datetime->createFromFormat($format, $dateStart);
        $endDate = $datetime->createFromFormat($format, $dateEnd);

        return $qb
                ->andWhere('((e.startDateTime <= :startDate AND e.endDateTime >= :endDate) OR (e.startDateTime BETWEEN :startDate AND :endDate) OR (e.endDateTime BETWEEN :startDate AND :endDate))')
                ->setParameter('startDate', $startDate->setTime(00, 00, 00))
                ->setParameter('endDate', $endDate->setTime(23, 59, 59))
                ->orderBy('e.startDateTime', 'ASC')
                ->getQuery();
    }

    /**
     * @param Users $user
     * @param Accounts $account
     * @return \Doctrine\ORM\Query
     */
    public function getAll(Users $user, Accounts $account)
    {
        return $this
            ->extract($user, $account)
            ->getQuery();
    }
}
