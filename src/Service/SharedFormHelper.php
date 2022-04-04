<?php

namespace App\Service;

use App\Entity\CaseNotes;
use App\Entity\SharedForm;
use App\Entity\SystemMessage;
use App\Enum\SharedFormServiceCommunicationChannel;
use App\Enum\SystemMessageStatus;
use Doctrine\ORM\EntityManagerInterface;

final class SharedFormHelper
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function generateInternalFormUrl(SharedForm $sharedForm): string
    {
        $moduleKey = $sharedForm->getFormData()->getForm()->getModule()->getKey();
        $urlPrefix = '';

        if ($moduleKey === 'activities_services') {
            $urlPrefix = 'activities-and-services/';
        }

        if ($moduleKey === 'assessment_outcomes') {
            $urlPrefix = 'assessment-and-outcomes/';
        }

        return '/admin/' . $urlPrefix . 'user/' . $sharedForm->getParticipantUser()->getId() . '/edit/' . $sharedForm->getFormData()->getId();
    }


    public function addSystemMessage(SharedForm $sharedForm): SystemMessage
    {
        $participantName = $sharedForm->getParticipantUser()->getData()->getFullName(false);
        $currentDate = new \DateTime();
        $createdDate = $sharedForm->getCompletedAt() ? $sharedForm->getCompletedAt()->format('m/d/Y') : $currentDate->format('m/d/Y');

        $messageUser = $sharedForm->getUser();

        $systemMessage = new SystemMessage;
        $systemMessage->setAccount($sharedForm->getAccount());
        $systemMessage->setTitle($participantName);

        if ($sharedForm->getStatus() == SharedForm::STATUS['FAILED']) {
            $systemMessage->setBody('Submission failed for ' . $participantName . ' on ' . $createdDate);
            $systemMessage->setType('shared_form_failed');
        }

        if ($sharedForm->getStatus() == SharedForm::STATUS['COMPLETED']) {
            $systemMessage->setBody('Submission completed for ' . $participantName . ' on ' . $createdDate);
            $systemMessage->setType('shared_form_completed');
        }

        $caseManagerId = $sharedForm->getParticipantUser()->getData()->getCaseManager();

        if ($caseManagerId) {
            $caseManager = $this->em->getRepository('App:Users')->find($caseManagerId);
            if ($caseManager) {
                $messageUser = $caseManager;
            }
        }

        $systemMessage->setAccount($sharedForm->getAccount());
        $systemMessage->setUser($messageUser);
        $systemMessage->setRelatedTo('participant');
        $systemMessage->setRelatedToId($sharedForm->getParticipantUser()->getId());
        $systemMessage->setStatus(SystemMessageStatus::UNREAD);
        $systemMessage->setCreatedAt(new \DateTime());

        $this->em->persist($systemMessage);
        $this->em->flush();

        return $systemMessage;
    }

    public function addFormSentCommunicationNote(SharedForm $sharedFormEntry, string $message): void
    {
        $type = '';

        if ($sharedFormEntry->getSentVia() == SharedFormServiceCommunicationChannel::EMAIL) {
            $type = 'email';
        }

        if ($sharedFormEntry->getSentVia() == SharedFormServiceCommunicationChannel::SMS) {
            $type = 'text';
        }

        $caseNote = new CaseNotes();
        $caseNote->setCreatedBy($sharedFormEntry->getUser());
        $caseNote->setCreatedAt(new \DateTime());
        $caseNote->setType($type);
        $caseNote->setNote($message);
        $caseNote->setParticipant($sharedFormEntry->getParticipantUser());
        $caseNote->setReadOnly(true);

        $this->em->persist($caseNote);
        $this->em->flush();
    }


}
