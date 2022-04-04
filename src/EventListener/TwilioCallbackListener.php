<?php

namespace App\EventListener;

use App\Entity\ActivityFeed;
use App\Entity\SharedForm;
use App\Event\SharedFormSendingFailedEvent;
use App\Event\SharedFormSentEvent;
use App\Event\TwilioCallbackDeliveredEvent;
use App\Event\TwilioCallbackErrorEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class TwilioCallbackListener
{
    private $em;
    private $eventDispatcher;
    private $sharedFormHelper;

    public function __construct(EntityManagerInterface $em, EventDispatcherInterface $eventDispatcher)
    {
        $this->em = $em;
        $this->eventDispatcher = $eventDispatcher;
        $this->sharedFormHelper = $sharedFormHelper;
    }

    public function onTwilioCallbackError(TwilioCallbackErrorEvent $event)
    {
        $message = $event->getMessage();
        $sid = $message->getSid();

        $sharedFormSmsMessage = $this->em->getRepository('App:SharedFormSmsMessage')->findOneBy(['sid' => $sid]);

        if (!$sharedFormSmsMessage) {
            return;
        }

        $sharedFormEntry = $sharedFormSmsMessage->getSharedForm();
        $sharedFormEntry->setStatus(SharedForm::STATUS['FAILED']);
        $this->em->persist($sharedFormEntry);
        $this->em->flush();
        $this->eventDispatcher->dispatch(
            new SharedFormSendingFailedEvent($sharedFormEntry),
            SharedFormSendingFailedEvent::class
        );
        $this->sharedFormHelper->addSystemMessage($sharedFormEntry);
    }

    public function onTwilioCallbackDelivered(TwilioCallbackDeliveredEvent $event)
    {
        $message = $event->getMessage();
        $sid = $message->getSid();

        $sharedFormSmsMessage = $this->em->getRepository('App:SharedFormSmsMessage')->findOneBy(['sid' => $sid]);

        if (!$sharedFormSmsMessage) {
            return;
        }

        $sharedFormEntry = $sharedFormSmsMessage->getSharedForm();
        $sharedFormEntry->setStatus(SharedForm::STATUS['SENT']);
        $this->em->persist($sharedFormEntry);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new SharedFormSentEvent($sharedFormEntry), SharedFormSentEvent::class);

    }
}
