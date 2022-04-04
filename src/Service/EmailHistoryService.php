<?php

namespace App\Service;

use App\Entity\EmailMessage;
use App\Entity\EmailRecipient;
use App\Entity\Users;
use App\Enum\EmailMessageStatus;
use App\Enum\EmailRecipientStatus;
use App\Worker\EmailSenderWorker;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Exception;

class EmailHistoryService
{
    protected $em;
    protected $emailSenderWorker;
    protected $emailMessage;

    public function __construct(EntityManagerInterface $em, EmailSenderWorker $emailSenderWorker)
    {
        $this->em = $em;
        $this->emailSenderWorker = $emailSenderWorker;
    }

    public function prepareIndex(array $historyEntries)
    {
        $messagesIndex = [];

        foreach ($historyEntries as $row) {
            $messagesIndex[] = [
                'id'         => $row->getId(),
                'subject'    => $row->getSubject(),
                'created_by' => $row->getCreator() ? $row->getCreator()->getData()->getFullName() : '',
                'created_at' => $row->getCreatedAt(),
                'sent_at'    => $row->getSentAt(),
                'status'     => $row->getStatus()

            ];
        }

        return $messagesIndex;
    }

    public function createEntry($emailData, Users $creator)
    {
        $emailMessage = new EmailMessage();
        $this->setEmailData($emailData, $creator, $emailMessage);

        $this->em->persist($emailMessage);
        $this->em->flush();

        foreach ($emailData['recipients'] as $recipientEmail) {
            $this->createRecipient($emailMessage, $recipientEmail);
        }

        $this->em->refresh($emailMessage);
        $this->emailMessage = $emailMessage;

        return $emailMessage;
    }

    public function updateEntry($emailData, Users $creator)
    {
        $emailMessage = $this->em->getRepository('App:EmailMessage')->find($emailData['id']);

        if (!$emailMessage) {
            throw new Exception('Wrong e-mail message!');
        }

        $this->setEmailData($emailData, $creator, $emailMessage);

        $oldRecipients = $emailMessage->getRecipients();

        foreach ($oldRecipients as $oldRecipient) {
            if (!in_array($oldRecipient->getEmail(), $emailData['recipients'])) {
                $this->em->remove($oldRecipient);
                $this->em->flush();
            }
        }

        foreach ($emailData['recipients'] as $recipientEmail) {
            $recipient = $this->em->getRepository('App:EmailRecipient')->findOneBy(['emailMessage'=>$emailMessage, 'email'=>$recipientEmail]);

            if (!$recipient) {
                $this->createRecipient($emailMessage, $recipientEmail);
            }
        }

        $this->em->refresh($emailMessage);
        $this->emailMessage = $emailMessage;

        return $emailMessage;
    }

    public function setMessage(EmailMessage $emailMessage)
    {
        $this->emailMessage = $emailMessage;
    }

    public function send()
    {
        if (!$this->emailMessage) {
            throw new Exception('Email message not set!');
        }

        $emailMessage = $this->emailMessage;
        $emailMessage->setStatus(EmailMessageStatus::SENDING);
        $this->em->flush();

        foreach ($this->emailMessage->getRecipients() as $recipient) {
            $this->emailSenderWorker->later()->sendMessage($emailMessage, $recipient);
        }
    }

    public function getEmailMessage()
    {
        return $this->emailMessage;
    }

    protected function createRecipient(EmailMessage $emailMessage, $recipientEmail): void
    {
        $emailRecipient = new EmailRecipient();
        $emailRecipient->setEmailMessage($emailMessage);
        $emailRecipient->setStatus(EmailRecipientStatus::NEW);
        $emailRecipient->setEmail($recipientEmail);

        $relatedUser = $this->em->getRepository('App:Users')->findOneBy(['emailCanonical' => $recipientEmail]);

        if ($relatedUser) {
            $emailRecipient->setUser($relatedUser);
            $emailRecipient->setName($relatedUser->getData()->getFirstName() . ' ' . $relatedUser->getData()->getLastName());
        }

        $emailRecipient->setLastActionDate(new \DateTime());

        $this->em->persist($emailRecipient);
        $this->em->flush();
    }

    /**
     * @param $emailData
     * @param Users $creator
     * @param EmailMessage $emailMessage
     * @return mixed
     * @throws \Exception
     */
    protected function setEmailData($emailData, Users $creator, EmailMessage &$emailMessage)
    {
        $emailMessage->setCreator($creator);
        $emailMessage->setSubject($emailData['subject']);
        $emailMessage->setHeader($emailData['header']);
        $emailMessage->setBody($emailData['body']);
        $emailMessage->setSender($emailData['sender']);
        $emailMessage->setRecipientsGroup($emailData['recipients_group']);
        $emailMessage->setRecipientsOption(is_array($emailData['recipients_option']) ? json_encode($emailData['recipients_option']) : $emailData['recipients_option']);
        $emailMessage->setCreatedAt(new \DateTime());
        $emailMessage->setStatus(EmailMessageStatus::DRAFTING);

        if (isset($emailData['template_id'])) {
            $template = $this->em->getRepository('App:EmailTemplate')->find($emailData['template_id']);

            if ($template) {
                $emailMessage->setTemplate($template);
            }
        }
    }
}
