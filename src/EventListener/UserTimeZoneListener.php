<?php

namespace App\EventListener;

use App\Event\UserTimeZoneEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserTimeZoneListener
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onTimeZoneUpdated(UserTimeZoneEvent $event)
    {
        $user = $event->getUser();
    }
}
