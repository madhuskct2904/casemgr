<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Assignments
 * @package App\Entity
 *
 * @ORM\Table(name="assignments")
 * @ORM\Entity(repositoryClass="App\Repository\AssignmentsRepository")
 */
class Assignments extends Entity
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
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $programStatusStartDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $programStatusEndDate;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $programStatus;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE", nullable=true)
     */
    protected $primaryCaseManager;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users", inversedBy="assignmentsP")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE", nullable=false)
     */
    protected $participant;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $avatar;

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
     * @return \DateTime
     */
    public function getProgramStatusStartDate(): ?\DateTime
    {
        return $this->programStatusStartDate;
    }

    /**
     * @param \DateTime $programStatusStartDate
     * @return $this
     */
    public function setProgramStatusStartDate(\DateTime $programStatusStartDate = null)
    {
        $this->programStatusStartDate = $programStatusStartDate;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getProgramStatusEndDate(): ?\DateTime
    {
        return $this->programStatusEndDate;
    }

    /**
     * @param \DateTime $programStatusEndDate
     * @return $this
     */
    public function setProgramStatusEndDate(\DateTime $programStatusEndDate = null)
    {
        $this->programStatusEndDate = $programStatusEndDate;

        return $this;
    }

    /**
     * @return string
     */
    public function getProgramStatus(): ?string
    {
        return $this->programStatus;
    }

    /**
     * @param string $programStatus
     * @return $this
     */
    public function setProgramStatus(string $programStatus = null)
    {
        $this->programStatus = $programStatus;

        return $this;
    }

    /**
     * @return Users
     */
    public function getPrimaryCaseManager(): ?Users
    {
        return $this->primaryCaseManager;
    }

    /**
     * @param Users $primaryCaseManager
     * @return $this
     */
    public function setPrimaryCaseManager(Users $primaryCaseManager = null)
    {
        $this->primaryCaseManager = $primaryCaseManager;

        return $this;
    }

    /**
     * @return Users
     */
    public function getParticipant(): ?Users
    {
        return $this->participant;
    }

    /**
     * @param Users $participant
     * @return $this
     */
    public function setParticipant(Users $participant = null)
    {
        $this->participant = $participant;

        return $this;
    }

    /**
     * @return string
     */
    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    /**
     * @param string|null $avatar
     * @return $this
     */
    public function setAvatar(?string $avatar = null)
    {
        $this->avatar = $avatar;

        return $this;
    }
}
