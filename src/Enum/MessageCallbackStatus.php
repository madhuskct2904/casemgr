<?php


namespace App\Enum;

/**
 * Class MessageCallbackStatus
 * @package App\Enum
 */
abstract class MessageCallbackStatus extends BasicEnum
{
    const ACCEPTED = 'accepted';
    const QUEUED = 'queued';
    const SENDING = 'sending';
    const SENT = 'sent';
    const FAILED = 'failed';
    const DELIVERED = 'delivered';
    const UNDELIVERED = 'undelivered';
    const RECEIVING = 'receiving';
    const RECEIVED = 'received';
    const READ = 'read';
}
