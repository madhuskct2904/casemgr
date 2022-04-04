<?php

namespace App\Event;

use App\Entity\Accounts;
use App\Entity\Users;
use Symfony\Contracts\EventDispatcher\Event;

class UserActivityEvent extends Event
{
    const LOGIN_SUCCESS = 'user.login_success';
    const LOGIN_FAILURE = 'user.login_failure';
    const LOGOUT = 'user.logout';
    const SESSION_TIMEOUT = 'user.session_timeout';
    const SECURITY_VIOLATION = 'user.security_violation';
    const SWITCH_ACCOUNT = 'user.switch_account';
    const LAST_ACTION = 'user.last_action';

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
