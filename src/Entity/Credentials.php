<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Credentials
 * @package App\Entity
 *
 * @ORM\Table(name="credentials")
 * @ORM\Entity(repositoryClass="App\Repository\CredentialsRepository")
 */
class Credentials extends Entity
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var Accounts
     *
     * @ORM\ManyToOne(targetEntity="Accounts", inversedBy="credentials")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id")
     */
    protected $account;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users", inversedBy="credentials")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    /**
     * @var bool
     *
     * @ORM\Column(name="enabled", type="boolean", nullable=false)
     */
    protected $enabled = false;

    /**
     * @var int
     *
     * @ORM\Column(name="access", type="integer", nullable=false)
     */
    protected $access = 0;

    /**
     * @ORM\Column(name="widgets",type="text", nullable=true)
     */
    protected $widgets;

    /**
     * @var boolean
     *
     * @ORM\Column(name="is_virtual", type="boolean", nullable=false)
     */
    protected $virtual;

    /**
     * Credentials constructor.
     */
    public function __construct()
    {
        $this->virtual = false;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @param mixed $account
     * @return $this
     */
    public function setAccount($account)
    {
        $this->account = $account;

        return $this;
    }

    /**
     * @return Users
     */
    public function getUser(): Users
    {
        return $this->user;
    }

    /**
     * @param Users $user
     * @return $this
     */
    public function setUser(Users $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     * @return $this
     */
    public function setEnabled(bool $enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * @return int
     */
    public function getAccess(): int
    {
        return $this->access;
    }

    /**
     * @param int $access
     * @return $this
     */
    public function setAccess(int $access)
    {
        $this->access = $access;

        return $this;
    }

    /**
     * @return string
     */
    public function getWidgets()
    {
        return $this->widgets;
    }

    /**
     * @param string $widgets
     * @return $this
     */
    public function setWidgets($widgets)
    {
        $this->widgets = $widgets;

        return  $this;
    }

    /**
     * @return bool
     */
    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    /**
     * @param bool $virtual
     * @return $this
     */
    public function setVirtual(bool $virtual)
    {
        $this->virtual = $virtual;

        return $this;
    }
}
