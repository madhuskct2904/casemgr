<?php

namespace App\Enum;

abstract class ParticipantStatus extends BasicEnum
{
    const DISMISSED = 0;
    const ACTIVE = 1;
    const NONE = null;
}
