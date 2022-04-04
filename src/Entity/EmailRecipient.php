<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;
use Nucleos\UserBundle\Model\User;

/**
 * @ORM\Entity
 * @ORM\Table(name="emails_recipients")
 */
class EmailRecipient extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\EmailMessage")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")
     */
    protected $emailMessage;

    /**
     * @ORM\Column(type="string")
     */
    protected $email;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Users")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="SET NULL", nullable=true)
     */
    protected $user;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $sentAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $lastActionDate;

    /**
     * @ORM\Column(type="integer")
     */
    protected $status;


    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set email.
     *
     * @param string $email
     *
     * @return EmailRecipient
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set name.
     *
     * @param string|null $name
     *
     * @return EmailRecipient
     */
    public function setName($name = null)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set sentAt.
     *
     * @param \DateTime|null $sentAt
     *
     * @return EmailRecipient
     */
    public function setSentAt($sentAt = null)
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    /**
     * Get sentAt.
     *
     * @return \DateTime|null
     */
    public function getSentAt()
    {
        return $this->sentAt;
    }

    /**
     * Set lastActionDate.
     *
     * @param \DateTime|null $lastActionDate
     *
     * @return EmailRecipient
     */
    public function setLastActionDate($lastActionDate = null)
    {
        $this->lastActionDate = $lastActionDate;

        return $this;
    }

    /**
     * Get lastActionDate.
     *
     * @return \DateTime|null
     */
    public function getLastActionDate()
    {
        return $this->lastActionDate;
    }

    /**
     * Set status.
     *
     * @param int $status
     *
     * @return EmailRecipient
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set emailMessage.
     *
     * @param \App\Entity\EmailMessage|null $emailMessage
     *
     * @return EmailRecipient
     */
    public function setEmailMessage(\App\Entity\EmailMessage $emailMessage = null)
    {
        $this->emailMessage = $emailMessage;

        return $this;
    }

    /**
     * Get emailMessage.
     *
     * @return \App\Entity\EmailMessage|null
     */
    public function getEmailMessage()
    {
        return $this->emailMessage;
    }

    /**
     * @return null|Users
     */
    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function setUser(?Users $user)
    {
        $this->user = $user;

        return $this;
    }
}
