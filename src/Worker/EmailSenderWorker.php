<?php

namespace App\Worker;

use App\Entity\EmailMessage;
use App\Entity\EmailRecipient;
use App\Enum\EmailMessageStatus;
use App\Enum\EmailRecipientStatus;
use App\Service\EmailBodyParser;
use Doctrine\ORM\EntityManagerInterface;
use Dtc\QueueBundle\Model\Worker;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class EmailSenderWorker extends Worker
{
    protected $mailer;
    protected $em;
    protected $twig;
    protected $senders;
    protected $emailBodyParser;

    public function __construct(MailerInterface $mailer, EntityManagerInterface $em, Environment $twig, EmailBodyParser $emailBodyParser, array $emailSenders)
    {
        $this->mailer = $mailer;
        $this->em = $em;
        $this->twig = $twig;
        $this->senders = $emailSenders;
        $this->emailBodyParser = $emailBodyParser;
    }

    public function getName()
    {
        return 'emailSender';
    }


    public function sendMessage(EmailMessage $emailMessage, EmailRecipient $emailRecipient)
    {
        try {
            $recipient = $this->em->getRepository('App:EmailRecipient')->find($emailRecipient->getId());

            $this->emailBodyParser->setRawBody($emailMessage->getBody());
            $this->emailBodyParser->setRecipient($recipient);

            $subject = $emailMessage->getSubject();
            $header = $emailMessage->getHeader();
            $HTMLBody = $this->emailBodyParser->parse();
            $txtBody = strip_tags($HTMLBody);

            $senderEmail = $this->senders[$emailMessage->getSender()]['email'];
            $senderName = $this->senders[$emailMessage->getSender()]['name'];
            $senderTitle = $senderName;
            $senderAddress = new Address($senderEmail, $senderName);
            $recipientAddress = new Address($recipient->getEmail(), $recipient->getName());

            $message = (new TemplatedEmail())
                ->subject($subject)
                ->from($senderAddress)
                ->replyTo($senderAddress)
                ->to($recipientAddress)
                ->htmlTemplate('Emails/system_email.html.twig')
                ->textTemplate('Emails/system_email.txt.twig')
                ->context([
                    'title'          => $subject,
                    'header'         => $header,
                    'content'        => $HTMLBody,
                    'textContent'    => $txtBody,
                    'senderTitle'    => $senderTitle,
                    'recipientEmail' => $recipient->getEmail()
                ]);

            $this->mailer->send($message);

            $emailMessage->setSentAt(new \DateTime());
            $recipient->setStatus(EmailRecipientStatus::SENT);
            $this->em->flush();
        } catch (\Exception $e) {
            $recipient = $this->em->getRepository('App:EmailRecipient')->find($emailRecipient->getId());
            $recipient->setStatus(EmailRecipientStatus::ERROR);
            $this->em->flush();
        }

        $emailMessage = $this->em->getRepository('App:EmailMessage')->find($emailMessage->getId());
        $remainingRecipients = $this->em->getRepository('App:EmailRecipient')->findBy(['emailMessage' => $emailMessage, 'status' => EmailRecipientStatus::NEW]);

        if (!$remainingRecipients) {
            $emailMessage->setSentAt(new \DateTime());
            $emailMessage->setStatus(EmailMessageStatus::SENT);
            $this->em->flush();
        }
    }
}
