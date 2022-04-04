<?php

namespace App\Entity;

use App\Transformers\MessageStatusTransformer;
use Casemgr\Entity\Entity;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Messages
 * @package App\Entity
 *
 * @ORM\Table(name="messages")
 * @ORM\Entity(repositoryClass="App\Repository\MessagesRepository")
 */
class Messages extends Entity
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
     * @ORM\ManyToOne(targetEntity="Users", inversedBy="messagesP")
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false)
     */
    protected $participant;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(referencedColumnName="id", nullable=true)
     */
    protected $user;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(referencedColumnName="id", nullable=true)
     */
    protected $case_manager_secondary;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $fromPhone;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $toPhone;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    protected $body;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $type;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $status;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $createdAt;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $sid;

    /**
     * @var Assignments
     *
     * @ORM\ManyToOne(targetEntity="Assignments")
     * @ORM\JoinColumn(name="assignment_id", referencedColumnName="id", nullable=true)
     */
    protected $assignment;

    /**
     * @var MassMessages
     *
     * @ORM\ManyToOne(targetEntity="MassMessages", inversedBy="messages")
     * @ORM\JoinColumn(name="mass_message_id", referencedColumnName="id", nullable=true)
     */
    protected $massMessage;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $error;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipant(): Users
    {
        return $this->participant;
    }

    public function setParticipant(Users $participant): self
    {
        $this->participant = $participant;

        return $this;
    }

    public function getFromPhone(): string
    {
        return $this->fromPhone;
    }

    public function setFromPhone(string $fromPhone): self
    {
        $this->fromPhone = $fromPhone;

        return $this;
    }

    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function setUser(Users $user = null): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCaseManagerSecondary(): ?Users
    {
        return $this->case_manager_secondary;
    }

    public function setCaseManagerSecondary(Users $case_manager_secondary = null): self
    {
        $this->case_manager_secondary = $case_manager_secondary;

        return $this;
    }

    public function getToPhone(): ?string
    {
        return $this->toPhone;
    }

    public function setToPhone(?string $toPhone): self
    {
        $this->toPhone = $toPhone;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body)
    {
        $this->body = $body;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getStatusTransformed(): ?string
    {
        return MessageStatusTransformer::transform($this->getStatus());
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSid(): ?string
    {
        return $this->sid;
    }

    public function setSid(?string $sid): self
    {
        $this->sid = $sid;

        return $this;
    }

    public function getAssignment(): ?Assignments
    {
        return $this->assignment;
    }

    public function setAssignment(?Assignments $assignment): self
    {
        $this->assignment = $assignment;

        return $this;
    }

    public function getMassMessage(): ?MassMessages
    {
        return $this->massMessage;
    }

    public function setMassMessage(?MassMessages $massMessage): self
    {
        $this->massMessage = $massMessage;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;

        return $this;
    }
}
