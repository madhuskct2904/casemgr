<?php

namespace App\EventListener;

use App\Entity\ActivityFeed;
use App\Entity\CaseNotes;
use App\Entity\Forms;
use App\Entity\Referral;
use App\Enum\ParticipantType;
use App\Enum\ReferralStatus;
use App\Enum\SystemMessageStatus;
use App\Event\ParticipantRemovedEvent;
use App\Event\ReferralEnrolledEvent;
use App\Event\ReferralNotEnrolledEvent;
use App\Service\FormDataService;
use App\Service\Referrals\ReferralHelper;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class ReferralListener
{
    protected $container;
    protected ManagerRegistry $doctrine;
    protected MailerInterface $mailer;
    protected ReferralHelper $referralHelper;
    protected FormDataService $formDataService;

    public function __construct(
        ContainerInterface $container,
        ManagerRegistry $doctrine,
        MailerInterface $mailer,
        ReferralHelper $referralHelper,
        FormDataService $formDataService
    )
    {
        $this->container = $container;
        $this->doctrine = $doctrine;
        $this->mailer = $mailer;
        $this->referralHelper = $referralHelper;
        $this->formDataService = $formDataService;
    }

    public function onReferralEnrolled(ReferralEnrolledEvent $event)
    {
        $em = $this->doctrine->getManager();

        $data = $event->getData();

        $referralId = $data['referral_id'];
        $participantId = $data['participant_id'];

        $referral = $em->getRepository('App:Referral')->find($referralId);

        if (!$referral) {
            return;
        }

        $referral->setStatus(ReferralStatus::ENROLLED);
        $referral->setEnrolledParticipant($em->getRepository('App:Users')->find($participantId));
        $referral->setLastActionAt(new \DateTime);
        $referral->setLastActionUser($data['user']);
        $em->flush();

        $em->refresh($referral);

        $this->addCommunicationNote($referral);
        $this->markSystemMessageAsRead($referral);
        $this->sendEmailToReferral($referral);
        $this->invalidateReports($referral->getFormData()->getForm());

        # Record an ActivityFeed entry
        $activityFeed = new ActivityFeed();

        $participant = $em->getRepository('App:Users')->find($data['participant_id']);

        if ($participant->getUserDataType() == ParticipantType::INDIVIDUAL) {
            $participantName = $participant->getData()->getFirstName() . ' ' . $participant->getData()->getLastName();
        }

        if ($participant->getUserDataType() == ParticipantType::MEMBER) {
            $participantName = $participant->getData()->getName();
        }

        $activityFeed
            ->setParticipant($participant)
            ->setTemplate('referral_enrolled')
            ->setTemplateId($data['referral_id'])
            ->setTitle('Referral completed for ' . $participantName);

        $em->persist($activityFeed);
        $em->flush();
    }

    public function onReferralNotEnrolled(ReferralNotEnrolledEvent $event)
    {
        $em = $this->doctrine->getManager();

        $data = $event->getData();

        $referralId = $data['referral_id'];

        $referral = $em->getRepository('App:Referral')->find($referralId);

        if (!$referral) {
            return;
        }

        $comment = $data['comment'];

        $referral->setStatus(ReferralStatus::NOT_ENROLLED);
        $referral->setLastActionAt(new \DateTime());
        $referral->setLastactionUser($data['user']);
        $referral->setComment($comment);
        $em->flush();
        $em->refresh($referral);

        $this->markSystemMessageAsRead($referral);
        $this->sendEmailToReferral($referral);

        $this->invalidateReports($referral->getFormData()->getForm());

        // Record an ActivityFeed entry
        $activityFeed = new ActivityFeed();

        $referral = $em->getRepository('App:Referral')->find($data['referral_id']);
        $participantName = $this->referralHelper->getParticipantName($referral);

        $details = [
            'participantName' => $participantName
        ];

        $activityFeed
            ->setParticipant(null)
            ->setAccount($referral->getAccount())
            ->setTemplate('referral_not_enrolled')
            ->setTemplateId($data['referral_id'])
            ->setTitle('Referral completed for ' . $participantName)
            ->setDetails($details);

        $em->persist($activityFeed);
        $em->flush();
    }

    public function onParticipantRemove(ParticipantRemovedEvent $participantEvent)
    {
        $participant = $participantEvent->getParticipant();

        $em = $this->doctrine->getManager();

        $referrals = $em->getRepository('App:Referral')->findBy([
            'enrolledParticipant' => $participant
        ]);

        if (!$referrals) {
            return;
        }

        foreach ($referrals as $referral) {
            $formData = $referral->getFormData();
            $this->invalidateReports($formData->getForm());
            $em->remove($formData);

            $activityFeed = $em->getRepository('App:ActivityFeed')->findBy([
                'template'   => 'referral_not_enrolled',
                'templateId' => $referral->getId()
            ]);

            foreach ($activityFeed as $feedItem) {
                $em->remove($feedItem);
            }

            $em->remove($referral);
        }
    }

    private function markSystemMessageAsRead(Referral $referral)
    {
        $em = $this->doctrine->getManager();
        $em->getRepository('App:SystemMessage')->setStatusBy(['relatedTo' => 'referral', 'relatedToId' => $referral->getId()], SystemMessageStatus::READ);
    }


    private function sendEmailToReferral(Referral $referral)
    {
        $participantName = $this->referralHelper->getParticipantName($referral);

        $formData = $referral->getFormData();

        $organizationName = $formData->getAccount()->getOrganizationName();

        $this->formDataService->setFormData($formData);
        $recipientEmail = $this->formDataService->getMappedValue('destination_email');

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $sender = $this->container->getParameter('email_senders')['support'];

        $senderEmail = $sender['email'];
        $senderName = $sender['name'];
        $senderTitle = $senderName;
        $senderAddress = new Address($senderEmail, $senderName);

        $subject = 'Referral Completed for ' . $participantName;
        $header = 'Referral Completed for ' . $participantName;
        $HTMLBody = nl2br('<strong>' . $organizationName . '</strong> has successfully completed the referral for <strong>' . $participantName . '</strong>. For any questions related to this referral, please contact ' . $organizationName . ' directly.');
        $txtBody = strip_tags($HTMLBody);

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

    private function addCommunicationNote(Referral $referral)
    {
        $note = '<a href="/admin/participant-referral/' . $referral->getEnrolledParticipant()->getId() . '"><strong>Referral</strong></a> completed.';

        $communicationNote = new CaseNotes();
        $communicationNote->setCreatedAt(new \DateTime);
        $communicationNote->setCreatedBy($referral->getLastActionUser());
        $communicationNote->setNote($note);
        $communicationNote->setParticipant($referral->getEnrolledParticipant());
        $communicationNote->setType('referral');

        $em = $this->doctrine->getManager();
        $em->persist($communicationNote);
        $em->flush();
    }

    private function invalidateReports(Forms $form)
    {
        $em = $this->doctrine->getManager();
        $em->getRepository('App:ReportsForms')->invalidateForm($form);
    }

}
