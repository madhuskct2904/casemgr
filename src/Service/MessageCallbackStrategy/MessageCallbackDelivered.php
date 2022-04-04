<?php


namespace App\Service\MessageCallbackStrategy;

use App\Dto\MessageCallback;
use App\Entity\Messages;
use App\Event\TwilioCallbackDeliveredEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class MessageCallbackError
 * @package App\Service\MessageCallbackStrategy
 */
class MessageCallbackDelivered implements MessageCallbackStrategyInterface
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

        $eventDispatcher->dispatch(new TwilioCallbackDeliveredEvent($message), TwilioCallbackDeliveredEvent::class);
    }
}
