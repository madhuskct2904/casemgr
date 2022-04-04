<?php

namespace App\Service\SharedFormMessageStrategy;

use App\Entity\SharedForm;
use App\Domain\SharedForms\SharedFormMessageException;
use App\Service\SharedFormHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;


final class ViaEmailStrategy implements SharedFormMessageChannelStrategyInterface
{
    private const STRATEGY_NAME = 'email';
    private $em;
    private $mailer;
    private $senderEmail;
    private $twig;
    private $sharedFormHelper;
    private $status;

    public function __construct(EntityManagerInterface $em, MailerInterface $mailer, array $emailSenders, Environment $twig, SharedFormHelper $sharedFormHelper)
    {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->senderEmail = $emailSenders['noreply'];
        $this->twig = $twig;
        $this->sharedFormHelper = $sharedFormHelper;
        $this->status = new SharedFormMessageStatus();
    }

    public function getStrategyName(): string
    {
        return self::STRATEGY_NAME;
    }

    public function send(SharedForm $sharedForm): void
    {
        $account = $sharedForm->getAccount();
        $participant = $sharedForm->getParticipantUser();
        $name = $participant->getData()->getName();

        $subject = $account->getOrganizationName().' has sent you a form to complete.';

        $profileData = $this->em->getRepository('App:Users')->getProfileData($participant, ['email'], $account->getProfileModuleKey());

        if (!isset($profileData['email']) || empty($profileData['email'])) {
            throw new SharedFormMessageException('Invalid email!');
        }

        $recipientEmail = $profileData['email'];
        $url = 'https://'.$account->getData()->getAccountUrl().'/form/'.$sharedForm->getUid();

        try {
            $message = (new TemplatedEmail())
                ->subject($subject)
                ->from($this->senderEmail['email'])
                ->to($recipientEmail)
                ->htmlTemplate('Emails/form_to_be_completed.html.twig')
                ->textTemplate('Emails/form_to_be_completed.txt.twig')
                ->context([
                    'title'          => $subject,
                    'user'           => $name,
                    'url'            => $url,
                    'account'        => $account->getOrganizationName(),
                    'recipientEmail' => $recipientEmail
                ]);

            $this->mailer->send($message);

        } catch (\Exception $e) {
            throw new SharedFormMessageException($e->getMessage());
        }

        $url = $this->sharedFormHelper::generateInternalFormUrl($sharedForm);

        $this->status->setStatus(SharedFormMessageStatus::STATUS_SUCCESS);
        $this->status->setMessage($sharedForm->getUser()->getData()->getFullName(true) . ' sent <a href="'.$url.'">' . $sharedForm->getFormData()->getForm()->getName() . '</a> via Email ' . $recipientEmail.'.');
    }

    public function getStatus(): SharedFormMessageStatus
    {
        return $this->status;
    }
}
