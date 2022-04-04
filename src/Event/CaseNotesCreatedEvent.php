<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class CaseNotesCreatedEvent
 * @package App\Event
 */
class CaseNotesCreatedEvent extends Event
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
