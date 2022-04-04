<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Referral
 *
 * @ORM\Table(name="referral")
 * @ORM\Entity(repositoryClass="App\Repository\ReferralRepository")
 */
class Referral
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
     * @var \App\Entity\FormsData
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\FormsData")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="data_id", referencedColumnName="id")
     * })
     */
    private $formData;

    /**
     * @var Accounts
     *
     * @ORM\ManyToOne(targetEntity="Accounts", inversedBy="referrals")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id")
     */
    private $account;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=63)
     */
    private $status;

    /**
     * @var string
     *
     * @ORM\Column(name="comment", type="text")
     */
    private $comment;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=false)
     */
    private $createdAt;

    /**
     * @var \App\Entity\Users
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="last_action_user", referencedColumnName="id")
     * })
     */
    private $lastActionUser;


    /**
     * @var \App\Entity\Users
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Users")
     * @ORM\JoinColumn(name="enrolled_participant_id", referencedColumnName="id", nullable=true)
    */
    private $enrolledParticipant;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_action_at", type="datetime", nullable=true)
     */
    private $lastActionAt;

    /**
     * @var string
     *
     * @ORM\Column(name="submission_token", type="string", length=40)
     */
    private $submissionToken;

    /**
     * Referral constructor.
     */
    public function __construct()
    {
        $this->comment = '';
        $this->createdAt = new \DateTime();
    }


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
     * Set status.
     *
     * @param string $status
     *
     * @return Referral
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
     * Set comment.
     *
     * @param string $comment
     *
     * @return Referral
     */
    public function setComment($comment)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Get comment.
     *
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return Referral
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
     * Set lastActionAt.
     *
     * @param \DateTime $lastActionAt|null
     *
     * @return Referral
     */
    public function setLastActionAt($lastActionAt)
    {
        $this->lastActionAt = $lastActionAt;

        return $this;
    }

    /**
     * Get lastActionAt.
     *
     * @return \DateTime
     */
    public function getLastActionAt()
    {
        return $this->lastActionAt;
    }

    /**
     * Set formData.
     *
     * @param \App\Entity\FormsData|null $formData
     *
     * @return Referral
     */
    public function setFormData(\App\Entity\FormsData $formData = null)
    {
        $this->formData = $formData;

        return $this;
    }

    /**
     * Get formData.
     *
     * @return \App\Entity\FormsData|null
     */
    public function getFormData()
    {
        return $this->formData;
    }

    /**
     * Set account.
     *
     * @param \App\Entity\Accounts|null $account
     *
     * @return Referral
     */
    public function setAccount(\App\Entity\Accounts $account = null)
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Get account.
     *
     * @return \App\Entity\Accounts|null
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * Set lastActionUser.
     *
     * @param \App\Entity\Users|null $lastActionUser
     *
     * @return Referral
     */
    public function setLastActionUser(\App\Entity\Users $lastActionUser = null)
    {
        $this->lastActionUser = $lastActionUser;

        return $this;
    }

    /**
     * Get lastActionUser.
     *
     * @return \App\Entity\Users|null
     */
    public function getLastActionUser()
    {
        return $this->lastActionUser;
    }

    /**
     * Set enrolledParticipant.
     *
     * @param \App\Entity\Users|null $enrolledParticipant
     *
     * @return Referral
     */
    public function setEnrolledParticipant(\App\Entity\Users $enrolledParticipant = null)
    {
        $this->enrolledParticipant = $enrolledParticipant;

        return $this;
    }

    /**
     * Get enrolledParticipant.
     *
     * @return \App\Entity\Users|null
     */
    public function getEnrolledParticipant()
    {
        return $this->enrolledParticipant;
    }

    /**
     * @return string
     */
    public function getSubmissionToken(): string
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
