<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Events
 * @package App\Entity
 *
 * @ORM\Table(name="events")
 * @ORM\Entity(repositoryClass="App\Repository\EventsRepository")
 */
class Events extends Entity
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
     * @ORM\Column(name="title",type="text", length=4096)
     */
    protected $title;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users", inversedBy="events")
     * @ORM\JoinColumn(name="participant_id", referencedColumnName="id", nullable=true)
     */
    protected $participant;

    /**
     * @var Accounts
     *
     * @ORM\ManyToOne(targetEntity="Accounts", inversedBy="credentials")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id")
     */
    protected $account;

    /**
     * @var CaseNotes
     *
     * @ORM\ManyToOne(targetEntity="CaseNotes", inversedBy="events")
     * @ORM\JoinColumn(name="case_note_id", referencedColumnName="id", nullable=true)
     */
    protected $caseNote;

    /**
     * @ORM\Column(name="all_day", type="string", columnDefinition="enum('0', '1')")
     */
    protected $allDay = '0';

    /**
     * @var \DateTime
     * @ORM\Column(name="start_date_time", type="datetime", nullable=false)
     */
    protected $startDateTime;

    /**
     * @var \DateTime
     * @ORM\Column(name="end_date_time", type="datetime", nullable=false)
     */
    protected $endDateTime;

    /**
     * @ORM\Column(name="comment", type="text", nullable=true)
     */
    protected $comment;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $modifiedAt;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(referencedColumnName="id")
     */
    protected $createdBy;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(referencedColumnName="id")
     */
    protected $modifiedBy;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    /**
     * @return Users
     */
    public function getParticipant(): ?Users
    {
        return $this->participant;
    }

    /**
     * @param Users|null $participant
     */
    public function setParticipant(Users $participant = null)
    {
        $this->participant = $participant;
    }

    /**
     * @return mixed
     */
    public function getAllDay(): string
    {
        return $this->allDay;
    }

    /**
     * @param mixed $allDay
     */
    public function setAllDay(string $allDay)
    {
        $this->allDay = $allDay;
    }

    /**
     * @return bool
     */
    public function isAllDay(): bool
    {
        return $this->allDay === '1';
    }

    /**
     * @return \DateTime
     */
    public function getStartDateTime(): \DateTime
    {
        return $this->startDateTime;
    }

    /**
     * @param \DateTime $startDateTime
     */
    public function setStartDateTime(\DateTime $startDateTime)
    {
        $this->startDateTime = $startDateTime;
    }

    /**
     * @return \DateTime
     */
    public function getEndDateTime(): \DateTime
    {
        return $this->endDateTime;
    }

    /**
     * @param \DateTime $endDateTime
     */
    public function setEndDateTime(\DateTime $endDateTime)
    {
        $this->endDateTime = $endDateTime;
    }

    /**
     * @return string
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     */
    public function setComment(string $comment)
    {
        $this->comment = $comment;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime|null $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt = null)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getModifiedAt(): ?\DateTime
    {
        return $this->modifiedAt;
    }

    /**
     * @param \DateTime|null $modifiedAt
     */
    public function setModifiedAt(\DateTime $modifiedAt = null)
    {
        $this->modifiedAt = $modifiedAt;
    }

    /**
     * @return Users
     */
    public function getCreatedBy(): ?Users
    {
        return $this->createdBy;
    }

    /**
     * @param Users $createdBy
     */
    public function setCreatedBy(Users $createdBy)
    {
        $this->createdBy = $createdBy;
    }

    /**
     * @return Users
     */
    public function getModifiedBy(): ?Users
    {
        return $this->modifiedBy;
    }

    /**
     * @param Users|null $modifiedBy
     */
    public function setModifiedBy(Users $modifiedBy = null)
    {
        $this->modifiedBy = $modifiedBy;
    }

    /**
     * @return CaseNotes
     */
    public function getCaseNote()
    {
        return $this->caseNote;
    }

    /**
     * @param CaseNotes $caseNote
     */
    public function setCaseNote($caseNote)
    {
        $this->caseNote = $caseNote;
    }

    /**
     * @return Accounts
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @param Accounts $account
     */
    public function setAccount($account)
    {
        $this->account = $account;
    }
}
