<?php


namespace App\Service\MessageCallbackStrategy;

use App\Dto\MessageCallback;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

interface MessageCallbackStrategyInterface
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param EventDispatcherInterface $eventDispatcher
     * @param MessageCallback $messageCallback
     */
    public function handle(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher, MessageCallback $messageCallback): void;
}
