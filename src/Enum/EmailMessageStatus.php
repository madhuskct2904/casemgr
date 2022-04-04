<?php


namespace App\Enum;

class EmailMessageStatus extends BasicEnum
{
    const DRAFTING = 0;
    const SENDING = 1;
    const SENT = 2;
    const WARNING = 3;
}
