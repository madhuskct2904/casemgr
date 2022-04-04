<?php


namespace App\Service\MessageCallbackStrategy;

use App\Dto\MessageCallback;
use App\Entity\Messages;
use App\Enum\MessageStrings;
use App\Event\TwilioCallbackErrorEvent;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class MessageCallbackError
 * @package App\Service\MessageCallbackStrategy
 */
class MessageCallbackError implements MessageCallbackStrategyInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @param EntityManagerInterface $entityManager
     * @param EventDispatcherInterface $eventDispatcher
     * @param MessageCallback $messageCallback
     *
     */
    public function handle(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher, MessageCallback $messageCallback): void
    {
        $this->entityManager = $entityManager;

        $repository = $this->entityManager->getRepository('App:Messages');

        /** @var Messages $message */
        $message = $repository->findOneBy([
            'sid' => $messageCallback->getMessageSid()
        ]);

        if ($message === null) {
            return;
        }

        $this->updateMessage($message, $messageCallback->getErrorCode());

        $eventDispatcher->dispatch(new TwilioCallbackErrorEvent($message), TwilioCallbackErrorEvent::class);
    }

    /**
     * @param Messages $message
     * @param string $error
     */
    private function updateMessage(Messages $message, string $error): void
    {
        $message->setStatus(MessageStrings::ERROR_MESSAGE_STATUS);
        $message->setError($error);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->createErrorMessage($message, $error);
    }

    /**
     * @param Messages $message
     * @param $error
     *
     * @throws Exception
     */
    private function createErrorMessage(Messages $message, string $error): Messages
    {
        $errorMessage = new Messages();

        $errorMessage->setBody(MessageStrings::ERROR_MESSAGE);
        $errorMessage->setUser($message->getUser());
        $errorMessage->setFromPhone($message->getFromPhone());
        $errorMessage->setParticipant($message->getParticipant());
        $errorMessage->setToPhone($message->getToPhone());
        $errorMessage->setStatus(MessageStrings::ERROR_RESPONSE_MESSAGE_STATUS);
        $errorMessage->setType(MessageStrings::INBOUND);
        $errorMessage->setCreatedAt(new DateTime());
        $errorMessage->setSid(null);
        $errorMessage->setMassMessage($message->getMassMessage());
        $errorMessage->setError($error);

        $this->entityManager->persist($errorMessage);
        $this->entityManager->flush();

        return $errorMessage;
    }
}
