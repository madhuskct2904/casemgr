<?php


namespace App\Enum;

class MessageStrings extends BasicEnum
{
    const ERROR_MESSAGE = 'There was an error in sending the previous message. Please try again later or contact the participant directly.';
    const ERROR_MESSAGE_STATUS = 'error';
    const ERROR_RESPONSE_MESSAGE_STATUS = 'system_administrator';
    const INBOUND = 'inbound';
    const OUTBOUND = 'outbound';
}
