<?php

namespace App\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventDispatcherFactoryService
{
    /**
     * This class is just a thin wrapper around the
     * `Symfony\Component\EventDispatcher\EventDispatcherInterface` class.
     * DO NOT USE IT unless you have no other options (e.g. in a `Controller` class
     * helper method where dependency injection is unavailable.)  If you need the event
     * dispatcher in a place where DI is available, use
     * `Symfony\Component\EventDispatcher\EventDispatcherInterface` directly instead.
     */
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }
}
