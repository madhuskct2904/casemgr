<?php

namespace App\EventListener;

use App\Entity\ActivityFeed;
use App\Entity\Messages;
use App\Event\MessagesCreatedEvent;
use App\Service\SharedFormHelper;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * Class MessagesListener
 * @package App\Event
 */
class MessagesListener
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * event data['message'] = Entity\Messages
     *
     * @param MessagesCreatedEvent $event
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function onMessagesCreated(MessagesCreatedEvent $event)
    {
        $data = $event->getData();
        $activityFeed = new ActivityFeed();

        if ($data['message'] instanceof Messages) {
            $activityFeed
                ->setParticipant($data['message']->getParticipant())
                ->setTemplate('messages_' . $data['message']->getType())
                ->setTemplateId($data['message']->getId())
                ->setTitle($data['message']->getUser() ? $data['message']->getUser()->getData()->getFullName() : null);

            $this->em->persist($activityFeed);
            $this->em->flush();
        }
    }
}
