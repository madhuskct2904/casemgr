<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="users_sessions")
 */
class UsersSessions extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="token",type="string", length=32, nullable=false)
     */
    protected $token;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_date",type="datetime", nullable=false)
     */
    protected $created_date;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="expired_date",type="datetime", nullable=false)
     */
    protected $expired_date;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_action_date",type="datetime", nullable=false)
     */
    protected $last_action_date;

    /**
     * @ORM\OneToOne(targetEntity="Users")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    /**
     * @ORM\Column(name="account",type="string", length=160, nullable=true)
     */
    protected $account;

    /**
     * @return mixed
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param mixed $token
     */
    public function setToken(string $token)
    {
        $this->token = $token;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedDate(): \DateTime
    {
        return $this->created_date;
    }

    /**
     * @param \DateTime $created_date
     *
     * @return $this
     */
    public function setCreatedDate(\DateTime $created_date)
    {
        $this->created_date = $created_date;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getExpiredDate(): \DateTime
    {
        return $this->expired_date;
    }

    /**
     * @param \DateTime $expired_date
     *
     * @return $this
     */
    public function setExpiredDate(\DateTime $expired_date)
    {
        $this->expired_date = $expired_date;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getLastActionDate(): \DateTime
    {
        return $this->last_action_date;
    }

    /**
     * @param \DateTime $last_action_date
     *
     * @return $this
     */
    public function setLastActionDate(\DateTime $last_action_date)
    {
        $this->last_action_date = $last_action_date;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUser(): Users
    {
        return $this->user;
    }
    /**
     * @param mixed $user
     */
    public function setUser(Users $user)
    {
        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @param string $account
     */
    public function setAccount($account)
    {
        $this->account = $account;
    }
}
