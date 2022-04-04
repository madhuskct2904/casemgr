<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EmailTemplateRepository")
 * @ORM\Table(name="email_template")
 */

class EmailTemplate extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected $name;

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
     * @ORM\Column(name="sender", type="string", length=100)
     */
    protected $sender;

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
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="SET NULL", nullable=true)
     */
    protected $modifiedBy;

    /**
     * @ORM\Column(name="modified_at", type="datetime", nullable=true)
     */
    protected $modifiedAt;

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
     * Set name.
     *
     * @param string $name
     *
     * @return EmailTemplate
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set subject.
     *
     * @param string $subject
     *
     * @return EmailTemplate
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
     * @return EmailTemplate
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
     * @return EmailTemplate
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
     * @return EmailTemplate
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
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return EmailTemplate
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
     * Set creator.
     *
     * @param \App\Entity\Users|null $creator
     *
     * @return EmailTemplate
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
     * Set modifiedAt.
     *
     * @param \DateTime $modifiedAt
     *
     * @return EmailTemplate
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    /**
     * Get modifiedAt.
     *
     * @return \DateTime
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * Set modifiedBy.
     *
     * @param \App\Entity\Users|null $modifiedBy
     *
     * @return EmailTemplate
     */
    public function setModifiedBy(\App\Entity\Users $modifiedBy = null)
    {
        $this->modifiedBy = $modifiedBy;

        return $this;
    }

    /**
     * Get modifiedBy.
     *
     * @return \App\Entity\Users|null
     */
    public function getModifiedBy()
    {
        return $this->modifiedBy;
    }
}
