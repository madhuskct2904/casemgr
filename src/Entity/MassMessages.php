<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class MassMessages
 * @package App\Entity
 *
 * @ORM\Table(name="mass_messages")
 * @ORM\Entity(repositoryClass="App\Repository\MassMessagesRepository")
 */
class MassMessages extends Entity
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
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(referencedColumnName="id", nullable=true)
     */
    protected $user;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    protected $body;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $createdAt;

    /**
     * @ORM\OneToMany(targetEntity="Messages", mappedBy="massMessage")
     */
    protected $messages;

    /**
     * @var Accounts
     *
     * @ORM\ManyToOne(targetEntity="Accounts")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id")
     */
    protected $account;

    /**
     * Messages constructor.
     */
    public function __construct()
    {
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Users|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param Users $user |null
     */
    public function setUser(Users $user = null)
    {
        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param string $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt(?\DateTimeZone $timeZone = null): DateTime
    {
        return null === $timeZone ? $this->createdAt : $this->createdAt->setTimezone($timeZone);
    }

    /**
     * @param DateTime $createdAt
     */
    public function setCreatedAt(DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @return Accounts|null
     */
    public function getAccount(): ?Accounts
    {
        return $this->account;
    }

    /**
     * @param Accounts|null $account
     */
    public function setAccount(Accounts $account = null)
    {
        $this->account = $account;
    }
}
