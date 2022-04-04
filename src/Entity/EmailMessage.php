<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EmailMessageRepository")
 * @ORM\Table(name="email_message")
 */
class EmailMessage extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="subject", type="string", length=255)
     */
    protected $subject;

    /**
     * @ORM\Column(name="header", type="string", length=100)
     */
    protected $header;

    /**
     * @ORM\Column(name="body", type="text")
     */
    protected $body;

    /**
     * @ORM\Column(name="sender", type="string", length=100, nullable=true)
     */
    protected $sender;

    /**
     * @ORM\Column(name="recipients_group", type="string", length=100, nullable=true)
     */
    protected $recipientsGroup;

    /**
     * @ORM\Column(name="recipients_option", type="text")
     */
    protected $recipientsOption;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\EmailRecipient", mappedBy="emailMessage", cascade={"remove"})
     */
    protected $recipients;

    /**
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="SET NULL", nullable=true)
     */
    protected $creator;

    /**
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected $createdAt;

    /**
     * @ORM\Column(name="sent_at", type="datetime", nullable=true)
     */
    protected $sentAt;

    /**
     * @ORM\Column(type="integer")
     */
    protected $status;

    /**
     * @ORM\ManyToOne(targetEntity="EmailTemplate")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="SET NULL", nullable=true)
     */
    protected $template;


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
     * Set subject.
     *
     * @param string $subject
     *
     * @return EmailMessage
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Get subject.
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Set header.
     *
     * @param string $header
     *
     * @return EmailMessage
     */
    public function setHeader($header)
    {
        $this->header = $header;

        return $this;
    }

    /**
     * Get header.
     *
     * @return string
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * Set body.
     *
     * @param string $body
     *
     * @return EmailMessage
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get body.
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Set sender.
     *
     * @param string $sender
     *
     * @return EmailMessage
     */
    public function setSender($sender)
    {
        $this->sender = $sender;

        return $this;
    }

    /**
     * Get sender.
     *
     * @return string
     */
    public function getSender()
    {
        return $this->sender;
    }


    /**
     * Set recipientsGroup.
     *
     * @param string $recipientsGroup
     *
     * @return EmailMessage
     */
    public function setRecipientsGroup($recipientsGroup)
    {
        $this->recipientsGroup = $recipientsGroup;

        return $this;
    }

    /**
     * Get recipientsGroup.
     *
     * @return string
     */
    public function getRecipientsGroup()
    {
        return $this->recipientsGroup;
    }

    /**
     * Set recipientsOption.
     *
     * @param string $recipientsOption
     *
     * @return EmailMessage
     */
    public function setRecipientsOption($recipientsOption)
    {
        $this->recipientsOption = $recipientsOption;

        return $this;
    }

    /**
     * Get recipientsOption.
     *
     * @return string
     */
    public function getRecipientsOption()
    {
        return $this->recipientsOption;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return EmailMessage
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set sentAt.
     *
     * @param \DateTime $sentAt
     *
     * @return EmailMessage
     */
    public function setSentAt($sentAt)
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    /**
     * Get sentAt.
     *
     * @return \DateTime
     */
    public function getSentAt()
    {
        return $this->sentAt;
    }

    /**
     * Set status.
     *
     * @param int $status
     *
     * @return EmailMessage
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
     * Set creator.
     *
     * @param \App\Entity\Users|null $creator
     *
     * @return EmailMessage
     */
    public function setCreator(\App\Entity\Users $creator = null)
    {
        $this->creator = $creator;

        return $this;
    }

    /**
     * Get creator.
     *
     * @return \App\Entity\Users|null
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * Set template.
     *
     * @param \App\Entity\EmailTemplate|null $template
     *
     * @return EmailMessage
     */
    public function setTemplate(\App\Entity\EmailTemplate $template = null)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Get template.
     *
     * @return \App\Entity\EmailTemplate|null
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set recipients.
     *
     * @param string $recipients
     *
     * @return EmailMessage
     */
    public function setRecipients($recipients)
    {
        $this->recipients = $recipients;

        return $this;
    }

    /**
     * Get recipients.
     *
     * @return string
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * Set recipient.
     *
     * @param \App\Entity\EmailRecipient|null $recipient
     *
     * @return EmailMessage
     */
    public function setRecipient(\App\Entity\EmailRecipient $recipient = null)
    {
        $this->recipient = $recipient;

        return $this;
    }

    /**
     * Get recipient.
     *
     * @return \App\Entity\EmailRecipient|null
     */
    public function getRecipient()
    {
        return $this->recipient;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->recipients = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add recipient.
     *
     * @param \App\Entity\EmailRecipient $recipient
     *
     * @return EmailMessage
     */
    public function addRecipient(\App\Entity\EmailRecipient $recipient)
    {
        $this->recipients[] = $recipient;

        return $this;
    }

    /**
     * Remove recipient.
     *
     * @param \App\Entity\EmailRecipient $recipient
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeRecipient(\App\Entity\EmailRecipient $recipient)
    {
        return $this->recipients->removeElement($recipient);
    }
}
