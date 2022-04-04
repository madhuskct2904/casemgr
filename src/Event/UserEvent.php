<?php

namespace App\Event;

use App\Entity\Accounts;
use App\Entity\Users;
use Symfony\Contracts\EventDispatcher\Event;

abstract class UserEvent extends Event
{
    protected $user;
    protected $accounts;
    protected $message;
    protected $details;

    public function __construct(Users $user, ?Accounts $accounts, string $message = '', array $details = [])
    {
        $this->user = $user;
        $this->accounts = $accounts;
        $this->message = $message;
        $this->details = $details;
    }

    /**
     * @return Users
     */
    public function getUser(): Users
    {
        return $this->user;
    }

    /**
     * @return Accounts|null
     */
    public function getAccounts(): ?Accounts
    {
        return $this->accounts;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
