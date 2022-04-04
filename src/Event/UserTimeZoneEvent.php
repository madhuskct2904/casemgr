<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class UserTimeZoneEvent extends Event
{
    const TIMEZONE_UPDATED = 'user.timezone.updated';

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
