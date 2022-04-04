<?php

namespace App\Service\SharedFormMessageStrategy;

class SharedFormMessageStatus
{
    const STATUS_SUCCESS = 'success';
    const STATUS_SENDING = 'sending';
    const STATUS_FAILED = 'failed';

    private $message = '';
    private $status;

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message): void
    {
        $this->message = $message;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status): void
    {
        $this->status = $status;
    }

}
