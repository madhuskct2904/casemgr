<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class MessagesCreatedEvent
 * @package App\Event
 */
class MessagesCreatedEvent extends Event
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
