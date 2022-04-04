<?php

namespace App\Event;

use App\Entity\SharedForm;
use Symfony\Contracts\EventDispatcher\Event;

class SharedFormSubmittedEvent extends Event
{
    protected $sharedForm;

    public function __construct(SharedForm $sharedForm)
    {
        $this->sharedForm = $sharedForm;
    }

    public function getSharedForm(): SharedForm
    {
        return $this->sharedForm;
    }
}
