<?php


namespace App\Service\MessageCallbackStrategy;

use App\Dto\MessageCallback as MessageCallbackDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class MessageCallback
 * @package App\Service\MessageCallbackStrategy
 */
class MessageCallback
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var MessageCallbackStrategyInterface
     */
    private $messageCallbackStrategy;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * MessageCallback constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param MessageCallbackStrategyInterface $messageCallbackStrategy
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher, MessageCallbackStrategyInterface $messageCallbackStrategy)
    {
        $this->entityManager = $entityManager;
        $this->messageCallbackStrategy = $messageCallbackStrategy;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param MessageCallbackDto $messageCallbackDto
     */
    public function handle(MessageCallbackDto $messageCallbackDto): void
    {
        $this->messageCallbackStrategy->handle($this->entityManager, $this->eventDispatcher, $messageCallbackDto);
    }
}
