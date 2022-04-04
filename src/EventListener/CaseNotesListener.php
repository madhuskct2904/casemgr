<?php

namespace App\EventListener;

use App\Entity\ActivityFeed;
use App\Event\CaseNotesCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * Class CaseNotesListener
 * @package App\Event
 */
class CaseNotesListener
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * event data['template'] | data['participant'] | data['template_id']
     *
     * @param CaseNotesCreatedEvent $event
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function onCaseNotesCreated(CaseNotesCreatedEvent $event)
    {
        $data = $event->getData();
        $activityFeed = new ActivityFeed();

        $activityFeed
            ->setParticipant($data['participant'])
            ->setTemplate($data['template'])
            ->setTemplateId($data['template_id']);

        if (isset($data['title'])) {
            $activityFeed->setTitle($data['title']);
        }

        if (isset($data['details'])) {
            $activityFeed->setDetails($data['details']);
        }

        $this->em->persist($activityFeed);
        $this->em->flush();
    }
}
