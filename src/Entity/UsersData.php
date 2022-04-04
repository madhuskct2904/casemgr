<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="users_data")
 */
class UsersData extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="gender",type="string", length=45, nullable=false)
     */
    protected $gender;

    /**
     * @ORM\Column(name="system_id",type="string", length=255, nullable=true)
     */
    protected $system_id;

    /**
     * @var string
     *
     * @ORM\Column(name="case_manager",type="string", length=255, nullable=true)
     */
    protected $case_manager;

    /**
     * @var integer
     *
     * @ORM\Column(name="case_manager_secondary",type="integer", nullable=true)
     */
    protected $case_manager_secondary;

    /**
     * @ORM\Column(name="status_label",type="string", length=100, nullable=true)
     */
    protected $statusLabel;

    /**
     * @ORM\Column(name="phone_number",type="string",length=255, nullable=true)
     */
    protected $phone_number;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="date_birth",type="string", length=255, nullable=true)
     */
    protected $date_birth;

    /**
     * @ORM\OneToOne(targetEntity="Users")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    /**
     * @ORM\Column(name="first_name", type="string", length=100, nullable=true)
     */
    protected $first_name;

    /**
     * @ORM\Column(name="last_name", type="string", length=100, nullable=true)
     */
    protected $last_name;

    /**
     * @ORM\Column(name="avatar", type="string", length=255, nullable=true)
     */
    protected $avatar;

    /**
     * @ORM\Column(name="job_title", type="string", length=255, nullable=true)
     */
    protected $job_title;

    /**
     * @ORM\Column(name="time_zone", type="string", length=255, nullable=true)
     */
    protected $time_zone;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="date_completed",type="string", length=255, nullable=true)
     */
    protected $date_completed;

    /**
     * @ORM\Column(name="organization_id", type="string", length=255, nullable=true)
     */
    protected $organizationId;


    /**
     * @ORM\Column(name="status", type="smallint", nullable=true)
     */
    protected $status;

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
     * @return string
     */
    public function getGender(): ?string
    {
        return $this->decrypt($this->gender);
    }


    /**
     * @param string $gender
     */
    public function setGender(string $gender)
    {
        $this->gender = $this->encrypt($gender);
    }

    /**
     * @return string|null
     */
    public function getSystemId(): ?string
    {
        return $this->decrypt($this->system_id);
    }

    /**
     * @param string $system_id
     */
    public function setSystemId(string $system_id)
    {
        $this->system_id = $this->encrypt($system_id);
    }

    /**
     * @return string|null
     */
    public function getCaseManager(): ?string
    {
        return $this->decrypt($this->case_manager);
    }

    /**
     * @param string $case_manager
     */
    public function setCaseManager(string $case_manager)
    {
        $this->case_manager = $this->encrypt($case_manager);
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
    public function setStatusLabel(?string $statusLabel)
    {
        $this->statusLabel = $this->encrypt($statusLabel);
    }

    /**
     * @return string|null
     */
    public function getPhoneNumber(): ?string
    {
        return $this->decrypt($this->phone_number);
    }

    /**
     * @param string $phone_number
     */
    public function setPhoneNumber(string $phone_number)
    {
        $this->phone_number = $this->encrypt($phone_number);
    }

    public function setDateBirth(?DateTime $date_birth = null): self
    {
        if (null === $date_birth) {
            $this->date_birth = null;

            return $this;
        }

        $this->date_birth = $this->encrypt($date_birth->format('Y-m-d'));

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDateBirth(): ?DateTime
    {
        if (!$this->date_birth) {
            return null;
        }
        return new DateTime($this->decrypt($this->date_birth));
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
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->decrypt($this->first_name);
    }

    /**
     * @param mixed $first_name
     */
    public function setFirstName(string $first_name)
    {
        $this->first_name = $this->encrypt($first_name);
    }

    /**
     * @return mixed
     */
    public function getLastName(): string
    {
        return $this->decrypt($this->last_name);
    }

    /**
     * @param mixed $last_name
     */
    public function setLastName(string $last_name)
    {
        $this->last_name = $this->encrypt($last_name);
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
    public function getJobTitle(): ?string
    {
        return $this->job_title;
    }

    /**
     * @param string $job_title
     */
    public function setJobTitle(string $job_title)
    {
        $this->job_title = $job_title;
    }

    /**
     * @return string
     */
    public function getTimeZone(): ?string
    {
        return $this->time_zone;
    }

    /**
     * @param string $time_zone
     */
    public function setTimeZone(string $time_zone)
    {
        $this->time_zone = $time_zone;
    }

    /**
     * @param DateTime $date_completed
     * @return $this
     */
    public function setDateCompleted(DateTime $date_completed)
    {
        $this->date_completed = $this->encrypt($date_completed->format('Y-m-d'));
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDateCompleted(): ?DateTime
    {
        if (!$this->date_completed) {
            return null;
        }
        return new DateTime($this->decrypt($this->date_completed));
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
        if ($comma) {
            return sprintf('%s%s %s', trim($this->getLastName()), ',', trim($this->getFirstName()));
        }

        return sprintf('%s %s', trim($this->getFirstName()), trim($this->getLastName()));
    }

    public function getName($firstOnly = true): string
    {
        if($firstOnly) {
            return $this->first_name;
        }

        return $this->first_name.' '.$this->last_name;
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
    public function setStatus(?int $status)
    {
        $this->status = $this->encrypt($status);
    }
}
