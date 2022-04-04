<?php

namespace App\Event;

use App\Entity\Messages;
use Symfony\Contracts\EventDispatcher\Event;

class TwilioCallbackErrorEvent extends Event
{
    private $message;

    public function __construct(Messages $message)
    {
        $this->message = $message;
    }

    /**
     * @return Messages
     */
    public function getMessage(): Messages
    {
        return $this->message;
    }

}
