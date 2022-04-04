<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class LoggerFactoryService
{
    /**
     * This class is just a thin wrapper around the `Psr\Log\LoggerInterface` class.
     * DO NOT USE IT unless you have no other options (e.g. in a `Controller` class
     * helper method where dependency injection is unavailable.)  If you need the logger
     * in a place where DI is available, use `Psr\Log\LoggerInterface` instead.
     */
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getLogger()
    {
        return $this->logger;
    }
}
