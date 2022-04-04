<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ReferralNotEnrolledEvent extends Event
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}
