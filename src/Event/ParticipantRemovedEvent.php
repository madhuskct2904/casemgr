<?php

namespace App\Event;

use App\Entity\Users;
use Symfony\Contracts\EventDispatcher\Event;

class ParticipantRemovedEvent extends Event
{
    protected $participant;

    public function __construct(Users $participant)
    {
        $this->participant = $participant;
    }

    /**
     * @return Users
     */
    public function getParticipant(): Users
    {
        return $this->participant;
    }

}
