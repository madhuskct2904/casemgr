<?php

namespace App\Domain\ReferralForms;

use App\Entity\FormsData;
use App\Entity\Referral;
use App\Entity\SystemMessage;
use App\Enum\ReferralStatus;
use App\Enum\SystemMessageStatus;
use App\Service\FormDataService;
use App\Service\Referrals\ReferralHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;


class ReferralEntryCreator
{
    protected $em;
    protected $mailer;
    protected $formData;
    protected $referral;
    protected $referralHelper;
    protected $formDataService;
    protected $emailSenders;

    public function __construct(
        EntityManagerInterface $em,
        MailerInterface $mailer,
        ReferralHelper $referralHelper,
        FormDataService $formDataService,
        array $emailSenders
    )
    {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->referralHelper = $referralHelper;
        $this->formDataService = $formDataService;
        $this->emailSenders = $emailSenders;
    }

    public function referralFilled(FormsData $formData): Referral
    {
        $this->formData = $formData;
        $this->referral = $this->addReferralEntry();

        $this->sendEmailNotification();
        $this->addSystemMessage();
        $this->invalidateReports();

        return $this->referral;
    }

    private function addReferralEntry()
    {
        $em = $this->em;
        $referral = new Referral();
        $referral->setStatus(ReferralStatus::PENDING);
        $referral->setFormData($this->formData);
        $referral->setAccount($this->formData->getAccount());
        $referral->setSubmissionToken(bin2hex(openssl_random_pseudo_bytes(20)));
        $em->persist($referral);
        $em->flush();
        $em->refresh($referral);

        $this->invalidateReports();

        return $referral;
    }

    private function sendEmailNotification()
    {
        $participantName = $this->referralHelper->getParticipantName($this->referral);
        $organizationName = $this->formData->getAccount()->getOrganizationName();

        $formDataService = $this->formDataService;
        $formDataService->setFormData($this->formData);
        $recipientEmail = $formDataService->getMappedValue('destination_email');

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $sender = $this->emailSenders['support'];

        $subject = 'Referral Submitted to ' . $organizationName;
        $header = 'Referral Submitted to ' . $organizationName;
        $HTMLBody = nl2br('The referral for <strong>' . $participantName . '</strong> has been successfully submitted to <strong>' . $organizationName . '</strong>. For any questions related to your submission, please contact ' . $organizationName . ' directly.');
        $txtBody = strip_tags($HTMLBody);

        $senderEmail = $sender['email'];
        $senderName = $sender['name'];
        $senderTitle = $senderName;
        $senderAddress = new Address($senderEmail, $senderName);

        $message = (new TemplatedEmail())
            ->subject($subject)
            ->from($senderAddress)
            ->replyTo($senderAddress)
            ->to($recipientEmail)
            ->htmlTemplate('Emails/system_email.html.twig')
            ->textTemplate('Emails/system_email.txt.twig')
            ->context([
                'title'          => $subject,
                'header'         => $header,
                'content'        => $HTMLBody,
                'textContent'    => $txtBody,
                'senderTitle'    => $senderTitle,
                'recipientEmail' => $recipientEmail
            ]);

        $this->mailer->send($message);
    }

    private function addSystemMessage()
    {
        $account = $this->formData->getAccount();

        $referralHelper = $this->referralHelper;
        $participantName = $referralHelper->getParticipantName($this->referral);

        $createdDate = $this->formData->getCreatedDate()->format('m/d/Y h:i A');

        $systemMessage = new SystemMessage;
        $systemMessage->setAccount($account);
        $systemMessage->setTitle('Referral');
        $systemMessage->setRelatedTo('referral');
        $systemMessage->setRelatedToId($this->referral->getId());
        $systemMessage->setBody('Referral received for ' . $participantName . ' on ' . $createdDate);
        $systemMessage->setStatus(SystemMessageStatus::UNREAD);
        $systemMessage->setType('referral');
        $systemMessage->setCreatedAt(new \DateTime());
        $em = $this->em;

        $em->persist($systemMessage);
        $em->flush();
    }

    private function invalidateReports()
    {
        $form = $this->formData->getForm();

        if (!$form) {
            return false;
        }

        $em = $this->em;
        $em->getRepository('App:ReportsForms')->invalidateForm($form);
    }
}
