<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SharedForm
 *
 * @ORM\Table(name="shared_form")
 * @ORM\Entity(repositoryClass="App\Repository\SharedFormRepository")
 */
class SharedForm
{
    const STATUS = [
        'SENDING'   => 'sending',
        'SENT'      => 'sent',
        'FAILED'    => 'failed',
        'COMPLETED' => 'completed'
    ];

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Accounts
     *
     * @ORM\ManyToOne(targetEntity="Accounts")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $account;

    /**
     * @var FormsData
     *
     * @ORM\OneToOne(targetEntity="FormsData")
     * @ORM\JoinColumn(name="form_data_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $formData;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(name="participant_user_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $participantUser;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $user;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="sent_at", type="datetime", nullable=true)
     */
    private $sentAt;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="completed_at", type="datetime", nullable=true)
     */
    private $completedAt;

    /**
     * @var string
     *
     * @ORM\Column(name="sent_via", type="string", length=31, nullable=true)
     */
    private $sentVia;

    /**
     * @var int
     *
     * @ORM\Column(name="status", type="string", length=31)
     */
    private $status;

    /**
     * @var string
     *
     * @ORM\Column(name="uid", type="string", length=63, unique=true)
     */
    private $uid;

    /**
     * @var string
     *
     * @ORM\Column(name="submission_token", type="string", length=40)
     */
    private $submissionToken;

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
     * @return Accounts|null
     */
    public function getAccount(): ?Accounts
    {
        return $this->account;
    }

    /**
     * @param Accounts|null $account
     *
     * @return SharedForm
     */
    public function setAccount(Accounts $account = null)
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Set formData.
     *
     * @param int $formData
     *
     * @return SharedForm
     */
    public function setFormData($formData)
    {
        $this->formData = $formData;

        return $this;
    }

    /**
     * Get formData.
     *
     * @return FormsData
     */
    public function getFormData()
    {
        return $this->formData;
    }

    /**
     * Set participantUser.
     *
     * @param Users $participantUser
     *
     * @return SharedForm
     */
    public function setParticipantUser($participantUser)
    {
        $this->participantUser = $participantUser;

        return $this;
    }

    /**
     * Get participantUser.
     *
     * @return Users
     */
    public function getParticipantUser()
    {
        return $this->participantUser;
    }


    /**
     * Set user.
     *
     * @param Users
     *
     * @return SharedForm
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return Users
     */
    public function getUser()
    {
        return $this->user;
    }


    /**
     * Set status.
     *
     * @param int $status
     *
     * @return SharedForm
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
     * Set sentVia.
     *
     * @param int $sentVia
     *
     * @return SharedForm
     */
    public function setSentVia($sentVia)
    {
        $this->sentVia = $sentVia;

        return $this;
    }

    /**
     * Get sentVia.
     *
     * @return string
     */
    public function getSentVia()
    {
        return $this->sentVia;
    }

    /**
     * @param \DateTime|null $sentAt
     */
    public function setSentAt(?\DateTime $sentAt): void
    {
        $this->sentAt = $sentAt;
    }

    /**
     * @return \DateTime|null
     */
    public function getSentAt(): ?\DateTime
    {
        return $this->sentAt;
    }

    /**
     * @param \DateTime|null $completedAt
     */
    public function setCompletedAt(?\DateTime $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    /**
     * @return \DateTime|null
     */
    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }

    /**
     * Set uid.
     *
     * @param string $uid
     *
     * @return SharedForm
     */
    public function setUid($uid)
    {
        $this->uid = $uid;

        return $this;
    }

    /**
     * Get uid.
     *
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @return string
     */
    public function getSubmissionToken(): ?string
    {
        return $this->submissionToken;
    }

    /**
     * @param string $submissionToken
     */
    public function setSubmissionToken(string $submissionToken): void
    {
        $this->submissionToken = $submissionToken;
    }

}
