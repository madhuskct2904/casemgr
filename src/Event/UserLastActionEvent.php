<?php

namespace App\Event;

use App\Entity\Accounts;
use App\Entity\Users;
use App\Event\UserEvent;
use Symfony\Contracts\EventDispatcher\Event;

class UserLastActionEvent extends UserEvent
{
    // `NAME` is used to reference this event in the user activity log
    const NAME = 'user.last_action';
}
