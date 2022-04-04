<?php

namespace App\Event;

use App\Entity\Accounts;
use App\Entity\Users;
use App\Event\UserEvent;
use Symfony\Contracts\EventDispatcher\Event;

class UserSessionTimeoutEvent extends UserEvent
{
    // `NAME` is used to reference this event in the user activity log
    const NAME = 'user.session_timeout';
}
