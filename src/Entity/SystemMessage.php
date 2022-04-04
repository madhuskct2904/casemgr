<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SystemMessage
 *
 * @ORM\Table(name="system_messages")
 * @ORM\Entity(repositoryClass="App\Repository\SystemMessageRepository")
 */
class SystemMessage
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */

    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255)
     *
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="body", type="text")
     */
    private $body;


    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=63)
     */
    private $type;


    /**
     * @var string
     *
     * @ORM\Column(name="related_to", type="string", length=63, nullable=true)
     */
    private $relatedTo;


    /**
     * @var int
     *
     * @ORM\Column(name="related_to_id", type="integer", nullable=true)
     */
    private $relatedToId;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=63)
     */
    private $status;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users", inversedBy="systemMessage")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true)
     */
    protected $user;

    /**
     * @var Accounts
     *
     * @ORM\ManyToOne(targetEntity="Accounts", inversedBy="systemMessage")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", nullable=true)
     */
    protected $account;


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
     * Set title.
     *
     * @param string $title
     *
     * @return SystemMessage
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set body.
     *
     * @param string $body
     *
     * @return SystemMessage
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
     * Set type.
     *
     * @param string $type
     *
     * @return SystemMessage
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set status.
     *
     * @param string $status
     *
     * @return SystemMessage
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return SystemMessage
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


    /**
     * Set relatedTo.
     *
     * @param string|null $relatedTo
     *
     * @return SystemMessage
     */
    public function setRelatedTo($relatedTo = null)
    {
        $this->relatedTo = $relatedTo;

        return $this;
    }

    /**
     * Get relatedTo.
     *
     * @return string|null
     */
    public function getRelatedTo()
    {
        return $this->relatedTo;
    }

    /**
     * Set relatedToId.
     *
     * @param int|null $relatedToId
     *
     * @return SystemMessage
     */
    public function setRelatedToId($relatedToId = null)
    {
        $this->relatedToId = $relatedToId;

        return $this;
    }

    /**
     * Get relatedToId.
     *
     * @return int|null
     */
    public function getRelatedToId()
    {
        return $this->relatedToId;
    }
}
