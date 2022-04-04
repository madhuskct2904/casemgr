<?php


namespace App\Service;

use App\Dto\MessageCallback;
use App\Enum\MessageCallbackStatus;
use App\Service\MessageCallbackStrategy\MessageCallbackDelivered;
use App\Service\MessageCallbackStrategy\MessageCallbackError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class MessageCallbackService
 * @package App\Service
 */
class MessageCallbackService
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var array
     */
    private $strategies = [
        MessageCallbackError::class => [
            MessageCallbackStatus::FAILED,
            MessageCallbackStatus::UNDELIVERED
        ],
        MessageCallbackDelivered::class => [
            MessageCallbackStatus::DELIVERED
        ]
    ];

    /**
     * MessageCallbackService constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher)
    {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param MessageCallback $messageCallback
     */
    public function handle(MessageCallback $messageCallback): void
    {
        $status = $messageCallback->getMessageStatus();

        foreach ($this->strategies as $strategy => $statuses) {
            if (in_array($status, $statuses)) {
                $callback = new MessageCallbackStrategy\MessageCallback($this->entityManager, $this->eventDispatcher, new $strategy());
                $callback->handle($messageCallback);
            }
        }
    }
}
