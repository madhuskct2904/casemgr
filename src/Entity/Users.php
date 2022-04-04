<?php

namespace App\Entity;

use App\Enum\ParticipantType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DateTime;
use Nucleos\UserBundle\Model\User as BaseUser;

/**
 * @ORM\Table(name="users")
 * @ORM\Entity(repositoryClass="App\Repository\UsersRepository")
 */
class Users extends BaseUser
{
    const ACCESS_LEVELS = [
        'REFERRAL_USER'         => 1,
        'VOLUNTEER'             => 2,
        'CASE_MANAGER'          => 3,
        'SUPERVISOR'            => 4,
        'PROGRAM_ADMINISTRATOR' => 5,
        'SYSTEM_ADMINISTRATOR'  => 6
    ];

    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     *
     * @ORM\OneToOne(targetEntity="App\Entity\UsersData", mappedBy="user", cascade={"remove"})
     */
    protected $individualData;

    /**
     *
     * @ORM\OneToOne(targetEntity="App\Entity\MemberData", mappedBy="user", cascade={"remove"})
     */
    protected $memberData;

    /**
     *
     * @ORM\OneToOne(targetEntity="App\Entity\UsersSessions", mappedBy="user", cascade={"remove"})
     */
    protected $session;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\UsersSettings", mappedBy="user", cascade={"remove"})
     */
    protected $settings;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $usernameCanonical;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $emailCanonical;

    /**
     * @var bool
     */
    protected $enabled;

    /**
     * The salt to use for hashing.
     *
     * @var string
     */
    protected $salt;

    /**
     * Encrypted password. Must be persisted.
     *
     * @var string
     */
    protected $password;

    /**
     * Plain password. Used for model validation. Must not be persisted.
     *
     * @var string
     */
    protected $plainPassword;

    /**
     * @var \DateTime
     */
    protected $lastLogin;

    /**
     * Random string sent to the user email address in order to verify it.
     *
     * @var string
     */
    protected $confirmationToken;

    /**
     * @var \DateTime
     */
    protected $passwordRequestedAt;

    /**
     * @var Collection
     */
    protected $groups;

    /**
     * @var array
     */
    protected $roles;

    /**
     * @ORM\Column(name="type", type="string", length=255, nullable=false)
     */
    protected $type;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="date")
     */
    protected $passwordSetAt;

    /**
     * @var ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="Accounts", inversedBy="users")
     * @ORM\JoinTable(name="users_accounts")
     */
    protected $accounts;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $defaultAccount;

    /**
     * @var Credentials
     *
     * @ORM\OneToMany(targetEntity="Credentials", mappedBy="user", cascade={"remove"})
     */
    protected $credentials;

    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\ActivityFeed", mappedBy="participant", cascade={"remove"})
     */
    protected $activityFeedsP;

    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\CaseNotes", mappedBy="participant", cascade={"remove"})
     */
    protected $caseNotesP;

    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Assignments", mappedBy="participant", cascade={"remove"})
     */
    protected $assignmentsP;

    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Events", mappedBy="participant", cascade={"remove"})
     */
    protected $eventsP;

    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Messages", mappedBy="participant", cascade={"remove"})
     */
    protected $messagesP;

    /**
     * @var int
     *
     * @ORM\Column(type="smallint")
     */
    protected $userDataType;

    protected $data;


    public function __construct()
    {
        $this->enabled          = false;
        $this->roles            = array();
        $this->settings         = new ArrayCollection();
        $this->accounts         = new ArrayCollection();
        $this->credentials      = new ArrayCollection();
        $this->passwordSetAt    = new \DateTime();
        $this->activityFeedsP   = new ArrayCollection();
        $this->caseNotesP       = new ArrayCollection();
        $this->assignmentsP     = new ArrayCollection();
        $this->messagesP        = new ArrayCollection();
        $this->eventsP          = new ArrayCollection();
    }

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
     * @return array
     */
    public function getRoles(): array
    {
        return $this->roles;
    }


    /**
     * @return MemberData
     */
    public function getMemberData(): ?MemberData
    {
        return $this->memberData;
    }

    /**
     * @param MemberData $memberData
     */
    public function setMemberData(MemberData $memberData)
    {
        $this->memberData = $memberData;
    }

    /**
     * @return UsersSessions
     */
    public function getSession(): UsersSessions
    {
        return $this->session;
    }

    /**
     * @return Collection
     */
    public function getSettings(): Collection
    {
        return $this->settings;
    }

    public function setTypeAsUser(): void
    {
        $this->type = 'user';
    }

    public function setTypeAsParticipant(): void
    {
        $this->type = 'participant';
    }

    /**
     * @return bool
     */
    public function isUser(): bool
    {
        return $this->type === 'user';
    }

    /**
     * @return bool
     */
    public function isParticipant(): bool
    {
        return $this->type === 'participant';
    }

    /**
     * @param \App\Entity\Accounts $account
     *
     * @return $this
     */
    public function addAccount(Accounts $account)
    {
        if (!$this->accounts->contains($account)) {
            $this->accounts->add($account);
        }

        return $this;
    }

    /**
     * @param \App\Entity\Accounts $account
     *
     * @return $this
     */
    public function removeAccount(Accounts $account)
    {
        if (!$this->accounts->contains($account)) {
            $this->accounts->removeElement($account);
        }

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getAccounts()
    {
        return $this->accounts;
    }

    /**
     * @return null|string
     */
    public function getDefaultAccount()
    {
        return $this->defaultAccount;
    }

    /**
     * @param $defaultAccount
     */
    public function setDefaultAccount($defaultAccount = null)
    {
        $this->defaultAccount = $defaultAccount;
    }

    /**
     * @return DateTime
     */
    public function getPasswordSetAt(): DateTime
    {
        return $this->passwordSetAt;
    }

    /**
     * @param DateTime $passwordSetAt
     */
    public function setPasswordSetAt(DateTime $passwordSetAt)
    {
        $this->passwordSetAt = $passwordSetAt;
    }

    /**
     * @param \App\Entity\Credentials $credential
     *
     * @return $this
     */
    public function addCredential(Credentials $credential)
    {
        if (!$this->credentials->contains($credential)) {
            $this->credentials->add($credential);
        }

        return $this;
    }

    /**
     * @param \App\Entity\Credentials $credential
     *
     * @return $this
     */
    public function removeCredential(Credentials $credential)
    {
        if (!$this->credentials->contains($credential)) {
            $this->credentials->removeElement($credential);
        }

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * @param Accounts|null $account
     * @return Credentials|null
     */
    public function getCredential(Accounts $account = null)
    {
        $credential = null;

        foreach ($this->credentials as $obj) {
            if ($obj->getAccount() === $account) {
                $credential = $obj;
                break;
            }
        }

        return $credential;
    }

    /**
     * @return ArrayCollection
     */
    public function getActivityFeedsP(): ArrayCollection
    {
        return $this->activityFeedsP;
    }

    /**
     * @return ArrayCollection
     */
    public function getCaseNotesP(): ArrayCollection
    {
        return $this->caseNotesP;
    }

    /**
     * @return ArrayCollection
     */
    public function getAssignmentsP(): ArrayCollection
    {
        return $this->assignmentsP;
    }

    /**
     * @return ArrayCollection
     */
    public function getMessagesP(): ArrayCollection
    {
        return $this->messagesP;
    }

    /**
     * @return ArrayCollection
     */
    public function getEventsP(): ArrayCollection
    {
        return $this->eventsP;
    }

    public function isSystemAdministrator()
    {
        $credentials = $this->credentials->filter(
            function ($credential) {
                return $credential->isEnabled() && $credential->getAccess() === $this::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'];
            }
        );

        return $credentials->first() ? true : false;
    }

    /**
     * @return int
     */
    public function getUserDataType(): int
    {
        return $this->userDataType;
    }

    /**
     * @param int $userDataType
     */
    public function setUserDataType(int $userDataType): void
    {
        $this->userDataType = $userDataType;
    }

    /**
     * @return mixed
     */
    public function getIndividualData()
    {
        return $this->individualData;
    }

    /**
     * @param mixed $individualData
     */
    public function setIndividualData($individualData): void
    {
        $this->individualData = $individualData;
    }


    public function getData()
    {
        if ($this->getUserDataType() === ParticipantType::INDIVIDUAL) {
            return $this->getIndividualData();
        }

        if ($this->getUserDataType() === ParticipantType::MEMBER) {
            return $this->getMemberData();
        }
    }


    public function clearAccounts(): self
    {
        $this->accounts->clear();
        return $this;
    }
}
