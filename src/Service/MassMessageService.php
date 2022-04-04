<?php


namespace App\Service;

use App\Entity\MassMessages;
use App\Entity\Users;
use App\Event\MassMessagesCreatedEvent;
use App\Exception\MassMessageServiceException;
use App\Exception\MessageServiceException;
use App\Utils\Helper;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class MassMessageService
 * @package App\Service
 */
class MassMessageService
{
    /**
     * @var MessageService
     */
    private $messageService;
    /**
     * @var ContainerInterface
     */
    private $container;
    /**
     * @var string
     */
    private $fromNumber;

    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * MassMessageService constructor.
     *
     * @param ContainerInterface $container
     * @param MessageService $messageService
     * @param ManagerResgitry $doctrine
     */
    public function __construct(
        ContainerInterface $container,
        MessageService $messageService,
        ManagerRegistry $doctrine,
        EventDispatcherInterface $eventDispatcher
    )
    {
        $this->messageService  = $messageService;
        $this->container       = $container;
        $this->doctrine        = $doctrine;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param $number
     */
    public function setFromNumber($number)
    {
        $this->fromNumber = $number;
    }

    /**
     * @return string
     */
    public function getFromNumber()
    {
        return $this->fromNumber;
    }

    /**
     * @param Users $user
     * @param array $participantIds
     * @param string $body
     *
     * @return array
     * @throws Exception
     */
    public function sendMessage(Users $user, array $participantIds, string $body): array
    {
        $participants = $this->getParticipants($participantIds);

        $accountData = $this->getDoctrine()->getRepository('App:AccountsData')->findOneBy([
            'accountUrl' => $user->getDefaultAccount()
        ]);
        $account = $this->getDoctrine()->getRepository('App:Accounts')->find($accountData->getId());

        $massMessage = new MassMessages();
        $massMessage->setBody($body);
        $massMessage->setCreatedAt(new DateTime());
        $massMessage->setUser($user);
        $massMessage->setAccount($account);

        $em = $this->getDoctrine()->getManager();
        $em->persist($massMessage);
        $em->flush();

        $statistics = [
            'success' => 0,
            'error'   => 0,
            'all'     => count($participantIds)
        ];

        foreach ($participants as $participant) {
            $data = $this->sendMessageToParticipant(
                $participant,
                $body
            );

            $this->messageService->storeMessage($data, $user, $massMessage);

            if ($data['error']) {
                ++$statistics['error'];
            } else {
                ++$statistics['success'];
            }
        }

        $this->eventDispatcher->dispatch(
            new MassMessagesCreatedEvent(['massMessage' => $massMessage]),
            MassMessagesCreatedEvent::class
        );

        return $statistics;
    }

    /**
     * @param array $participantIds
     *
     * @return array
     * @throws Exception
     */
    protected function getParticipants(array $participantIds): array
    {
        $participants = [];

        foreach ($participantIds as $participantId) {
            $participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                'id'   => $participantId,
                'type' => 'participant'
            ]);

            $participants[] = $participant;
        }

        return $participants;
    }

    /**
     * @return object|null
     * @throws Exception
     */
    protected function getDoctrine()
    {
        return $this->doctrine;
    }

    /**
     * @param Users $participant
     *
     * @throws MassMessageServiceException
     */
    protected function checkParticipant(Users $participant)
    {
        if (! $participant) {
            throw new MassMessageServiceException("Invalid Participant.", 404);
        }

        if (! $pAccount = $participant->getAccounts()->first()) {
            throw new MassMessageServiceException("Invalid Participant Account.", 404);
        }

        $toNumber = Helper::convertPhone($participant->getData()->getPhoneNumber());

        if (! $toNumber) {
            throw new MassMessageServiceException("Invalid phone number.", 400);
        }
    }

    /**
     * @param Users $participant
     * @param string $body
     *
     * @return array
     */
    private function sendMessageToParticipant(Users $participant, string $body): array
    {
        $error    = null;
        $message  = null;
        $toNumber = Helper::convertPhone($participant->getData()->getPhoneNumber());

        try {
            $this->checkParticipant($participant);

            $message = $this->messageService->sendMessage(
                $body,
                $toNumber,
                $this->getFromNumber()
            );
        } catch (Exception $exception) {
            $error = $exception->getMessage();
        }

        return [
            'response' => $message,
            'message'  => [
                'body'        => $body,
                'fromPhone'   => $this->getFromNumber(),
                'participant' => $participant,
                'toPhone'     => $toNumber
            ],
            'error'    => $error
        ];
    }
}
