<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="members_data")
 */
class MemberData extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\OneToOne(targetEntity="Users")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    /**
     * @ORM\Column(name="system_id",type="string", length=255, nullable=true)
     */
    protected $systemId;

    /**
     * @var string
     *
     * @ORM\Column(name="case_manager",type="string", length=255, nullable=true)
     */
    protected $caseManager;

    /**
     * @ORM\Column(name="status", type="smallint", nullable=true)
     */
    protected $status;

    /**
     * @ORM\Column(name="status_label",type="string", length=100, nullable=true)
     */
    protected $statusLabel;

    /**
     * @ORM\Column(name="phone_number",type="string",length=255, nullable=true)
     */
    protected $phoneNumber;

    /**
     * @ORM\Column(name="name", type="string", length=100, nullable=true)
     */
    protected $name;

    /**
     * @ORM\Column(name="job_title", type="string", length=255, nullable=true)
     */
    protected $jobTitle;

    /**
     * @ORM\Column(name="avatar", type="string", length=255, nullable=true)
     */
    protected $avatar;

    /**
     * @ORM\Column(name="time_zone", type="string", length=255, nullable=true)
     */
    protected $timeZone;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_completed",type="string", length=255, nullable=true)
     */
    protected $dateCompleted;

    /**
     * @ORM\Column(name="organization_id", type="string", length=255, nullable=true)
     */
    protected $organizationId;

    /**
     * @var integer
     *
     * @ORM\Column(name="case_manager_secondary",type="integer", nullable=true)
     */
    protected $case_manager_secondary;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return Users
     */
    public function getUser(): Users
    {
        return $this->user;
    }

    /**
     * @param Users $user
     */
    public function setUser(Users $user)
    {
        $this->user = $user;
    }

    /**
     * @return string|null
     */
    public function getSystemId(): ?string
    {
        return $this->decrypt($this->systemId);
    }

    /**
     * @param string $system_id
     */
    public function setSystemId(string $system_id)
    {
        $this->systemId = $this->encrypt($system_id);
    }

    /**
     * @return string|null
     */
    public function getCaseManager(): ?string
    {
        return $this->decrypt($this->caseManager);
    }

    /**
     * @param string $case_manager
     */
    public function setCaseManager(string $case_manager)
    {
        $this->caseManager = $this->encrypt($case_manager);
    }

    /**
     * @return string|null
     */
    public function getStatusLabel(): ?string
    {
        return $this->decrypt($this->statusLabel);
    }

    /**
     * @param string $statusLabel
     */
    public function setStatusLabel(string $statusLabel)
    {
        $this->statusLabel = $this->encrypt($statusLabel);
    }

    /**
     * @return string|null
     */
    public function getPhoneNumber(): ?string
    {
        return $this->decrypt($this->phoneNumber);
    }

    /**
     * @param string $phone_number
     */
    public function setPhoneNumber(string $phone_number)
    {
        $this->phoneNumber = $this->encrypt($phone_number);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->decrypt($this->name);
    }

    /**
     * @return string
     */
    public function getJobTitle(): string
    {
        return $this->jobTitle;
    }

    /**
     * @param mixed $name
     */
    public function setName(string $first_name)
    {
        $this->name = $this->encrypt($first_name);
    }

    /**
     * @param mixed $jobTitle
     */
    public function setJobTitle(string $jobTitle)
    {
        $this->jobTitle = $jobTitle;
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
     */
    public function setAvatar(?string $avatar)
    {
        $this->avatar = $avatar;
    }

    /**
     * @return string
     */
    public function getTimeZone(): ?string
    {
        return $this->timeZone;
    }

    /**
     * @param string $time_zone
     */
    public function setTimeZone(string $time_zone)
    {
        $this->timeZone = $time_zone;
    }

    /**
     * @param \DateTime $date_completed
     * @return $this
     */
    public function setDateCompleted(\DateTime $date_completed)
    {
        $this->dateCompleted = $this->encrypt($date_completed->format('Y-m-d'));

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getDateCompleted(): \DateTime
    {
        return new \DateTime($this->decrypt($this->dateCompleted));
    }

    /**
     * @return mixed
     */
    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }

    /**
     * @param mixed $organization_id
     */
    public function setOrganizationId(string $organization_id)
    {
        $this->organizationId = $organization_id;
    }

    /**
     * @param bool $comma
     * @return string
     */
    public function getFullName($comma = true): string
    {
        return trim($this->getName());
    }

    /**
     * @return integer|null
     */
    public function getStatus(): ?int
    {
        return $this->decrypt($this->status);
    }

    /**
     * @param integer $status
     */
    public function setStatus(int $status)
    {
        $this->status = $this->encrypt($status);
    }

    /**
     * @return int|null
     */
    public function getCaseManagerSecondary(): ?int
    {
        return $this->case_manager_secondary;
    }

    /**
     * @param int $case_manager_secondary
     */
    public function setCaseManagerSecondary(?int $case_manager_secondary): void
    {
        $this->case_manager_secondary = $case_manager_secondary;
    }
}
