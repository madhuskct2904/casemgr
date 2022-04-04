<?php

namespace App\EventListener;

use App\Entity\ActivityFeed;
use App\Event\FormsValuesCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * Class FormsValuesListener
 * @package App\Event
 */
class FormsValuesListener
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * event data['template'] | data['participant_id'] | data['title'] | data['template_id']
     *
     * @param FormsValuesCreatedEvent $event
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function onFormsValuesCreated(FormsValuesCreatedEvent $event)
    {
        $data = $event->getData();
        $activityFeed = new ActivityFeed();

        $activityFeed
            ->setParticipant($this->em->getRepository('App:Users')->find($data['participant_id']))
            ->setTemplate($data['template'])
            ->setTitle($data['title'])
            ->setTemplateId($data['template_id']);

        if (isset($data['details'])) {
            $activityFeed->setDetails($data['details']);
        }

        $this->em->persist($activityFeed);
        $this->em->flush();
    }
}
