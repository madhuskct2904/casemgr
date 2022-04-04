<?php

namespace App\EventListener;

use App\Entity\ActivityFeed;
use App\Entity\SharedForm;
use App\Event\SharedFormSubmittedEvent;
use App\Event\SharedFormSentEvent;
use App\Event\SharedFormSendingFailedEvent;
use App\Event\TwilioCallbackEvent;
use App\Service\SharedFormHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class SharedFormListener
{
    private $em;
    private $eventDispatcher;
    private $sharedFormHelper;

    public function __construct(
        EntityManagerInterface $em,
        EventDispatcherInterface $eventDispatcher,
        SharedFormHelper $sharedFormHelper
    )
    {
        $this->em = $em;
        $this->eventDispatcher = $eventDispatcher;
        $this->sharedFormHelper = $sharedFormHelper;
    }

    public function onTwilioCallbackError(TwilioCallbackEvent $callbackEvent)
    {
        $message = $callbackEvent->getMessage();
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

    public function onTwilioCallbackDelivered(TwilioCallbackEvent $callbackEvent)
    {
        $message = $callbackEvent->getMessage();
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

    public function onSharedFormSubmitted(SharedFormSubmittedEvent $event)
    {
        $form = $event->getSharedForm()->getFormData()->getForm();
        $link = $this->sharedFormHelper::generateInternalFormUrl($event->getSharedForm());
        $title = $form->getModule()->getName() . ' <a href="' . $link . '.">' . $form->getName() . '</a>';
        return $this->createActivityFeed($event, 'shared_form_submission', $title);
    }

    public function onSharedFormSent(SharedFormSentEvent $event)
    {
        $form = $event->getSharedForm()->getFormData()->getForm();
        $link = $this->sharedFormHelper::generateInternalFormUrl($event->getSharedForm());
        $title = '<a href="' . $link . '.">' . $form->getName() . '</a>';
        return $this->createActivityFeed($event, 'shared_form_sent', $title);
    }

    public function onSharedFormSendingFailed(SharedFormSendingFailedEvent $event)
    {
        return $this->createActivityFeed($event, 'shared_form_failed');
    }

    private function createActivityFeed($sharedFormEvent, string $template, $title = null)
    {
        $sharedForm = $sharedFormEvent->getSharedForm();

        $activityFeed = new ActivityFeed();

        if (!$title) {
            $title = $sharedForm->getFormData()->getForm()->getName();
        }


        $details = [
            'userName'        => $sharedForm->getUser()->getData()->getFullName(true),
            'userId'          => $sharedForm->getUser()->getId(),
            'participantName' => $sharedForm->getParticipantUser()->getData()->getName(),
            'participantId'   => $sharedForm->getParticipantUser()->getId()
        ];

        $activityFeed
            ->setParticipant($sharedForm->getParticipantUser())
            ->setAccount($sharedForm->getAccount())
            ->setTemplate($template)
            ->setTemplateId($sharedForm->getId())
            ->setTitle($title)
            ->setDetails($details);

        $this->em->persist($activityFeed);
        $this->em->flush();
    }
}
