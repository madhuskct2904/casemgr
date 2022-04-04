<?php

namespace App\Service\SharedFormMessageStrategy;

use App\Entity\SharedForm;
use App\Entity\SharedFormSmsMessage;
use App\Domain\SharedForms\SharedFormMessageException;
use App\Exception\TwilioResponseException;
use App\Service\MessageService;
use App\Service\SharedFormHelper;
use App\Utils\Helper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ViaSMSStrategy implements SharedFormMessageChannelStrategyInterface
{
    private const STRATEGY_NAME = 'sms';
    private $em;
    private $messageService;
    private $eventDispatcher;
    private $status;
    private $callbackUrl;
    private $sharedFormHelper;

    public function __construct(EntityManagerInterface $em, MessageService $messageService, EventDispatcherInterface $eventDispatcher, string $callbackUrl, SharedFormHelper $sharedFormHelper)
    {
        $this->em = $em;
        $this->messageService = $messageService;
        $this->eventDispatcher = $eventDispatcher;
        $this->status = new SharedFormMessageStatus();
        $this->callbackUrl = $callbackUrl;
        $this->sharedFormHelper = $sharedFormHelper;
    }

    public function getStrategyName(): string
    {
        return self::STRATEGY_NAME;
    }

    public function send(SharedForm $sharedForm): void
    {
        $account = $sharedForm->getAccount();

        if (!$account->getTwilioStatus()) {
            $this->status->setStatus(SharedFormMessageStatus::STATUS_FAILED);
            $this->status->setMessage('Account has disabled SMS sending function.');
            throw new SharedFormMessageException('SMS sending failed. Account has disabled SMS communication.');
        }

        $participant = $sharedForm->getParticipantUser();
        $name = $participant->getData()->getName();

        $accountName = $account->getOrganizationName();
        $url = 'https://' . $account->getData()->getAccountUrl() . '/form/' . $sharedForm->getUid();

        $messageBody = "Hi $name! $accountName has sent you form to complete: $url";

        $messageService = $this->messageService;

        $fromNumber = Helper::convertPhone($account->getTwilioPhone());
        $toNumber = Helper::convertPhone($participant->getData()->getPhoneNumber());

        $messageData = [
            'response' => null,
            'message'  => [
                'body'        => $messageBody,
                'fromPhone'   => $fromNumber,
                'participant' => $participant,
                'toPhone'     => $toNumber,
            ],
            'error'    => null
        ];

        try {
            $smsMessage = $messageService->sendMessage(
                $messageBody,
                $toNumber,
                $fromNumber
            );

            $messageData['response'] = $smsMessage;
            $sharedFormSmsMessage    = $this->em->getRepository('App:SharedFormSmsMessage')->findOneBy([
                'sharedForm' => $sharedForm
            ]);

            if (!$sharedFormSmsMessage) {
                $sharedFormSmsMessage = new SharedFormSmsMessage();
            }

            $sharedFormSmsMessage->setSharedForm($sharedForm);
            $sharedFormSmsMessage->setSid($smsMessage['sid']);
            $this->em->persist($sharedFormSmsMessage);
            $this->em->flush();

        } catch (\Exception $exception) {
            $this->status->setStatus(SharedFormMessageStatus::STATUS_FAILED);
            $this->status->setMessage($sharedForm->getUser()->getData()->getFullName(true) . ' sent <a href="' . $url . '">' . $sharedForm->getFormData()->getForm()->getName() . '</a> via SMS ' . $toNumber . '.');

            $messageData['error'] = $exception->getMessage();
            throw new SharedFormMessageException('SMS sending failed.');
        } finally {
            $messageService->storeMessage($messageData, $participant);
        }

        $url = $this->sharedFormHelper::generateInternalFormUrl($sharedForm);

        $this->status->setStatus(SharedFormMessageStatus::STATUS_SUCCESS);
        $this->status->setMessage($sharedForm->getUser()->getData()->getFullName(true) . ' sent <a href="' . $url . '">' . $sharedForm->getFormData()->getForm()->getName() . '</a> via SMS ' . $toNumber . '.');
    }

    public function getStatus(): SharedFormMessageStatus
    {
        return $this->status;
    }
}
