<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class MassMessagesCreatedEvent
 * @package App\Event
 */
class MassMessagesCreatedEvent extends Event
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}
