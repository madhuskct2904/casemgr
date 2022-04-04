<?php

namespace App\Entity;

use App\Enum\AccountType;
use App\Enum\ParticipantType;
use Casemgr\Entity\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Accounts
 * @package App\Entity
 *
 * @ORM\Table(name="accounts")
 * @ORM\Entity(repositoryClass="App\Repository\AccountsRepository")
 * @UniqueEntity(fields={"twilioPhone"}, )
 */
class Accounts extends Entity
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
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     *
     * @Assert\NotBlank()
     * @Assert\Length(
     *     max = 100,
     * )
     */
    protected $organizationName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false, unique=true)
     */
    protected $systemId;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $accountType;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $activationDate;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    protected $status;

    /**
     * @var AccountsData
     *
     * @ORM\ManyToOne(targetEntity="AccountsData", cascade={"persist","remove"})
     * @ORM\JoinColumn(name="data_id", referencedColumnName="id", unique=true)
     *
     * @Assert\Valid()
     */
    protected $data;

    /**
     * @var ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="Users", mappedBy="accounts")
     *
     * @Assert\Valid()
     */
    protected $users;

    /**
     * @var Credentials
     *
     * @ORM\OneToMany(targetEntity="Credentials", mappedBy="account", cascade={"remove"})
     */
    protected $credentials;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean")
     */
    protected $main;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 20,
     * )
     */
    protected $twilioPhone;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean")
     */
    protected $twilioStatus;


    /**
     * @ORM\ManyToMany(targetEntity="Forms", mappedBy="accounts")
     */
    protected $forms;

    /**
     * @var int
     *
     * @ORM\Column(type="smallint")
     */
    protected $participantType;

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity="Accounts", mappedBy="parentAccount")
     */
    protected $childrenAccounts;

    /**
     * @ORM\ManyToOne(targetEntity="Accounts", inversedBy="childrenAccounts")
     * @ORM\JoinColumn(name="parent_account", referencedColumnName="id", nullable=TRUE)
     */
    protected $parentAccount;

    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="LinkedAccountHistory", mappedBy="account")
     */
    protected $linkedAccountHistory;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean", name="hipaa_regulated")
     */
    protected $HIPAARegulated;


    /**
     * @var string
     *
     * @ORM\Column(type="text", name="search_in_organizations", nullable=true)
     */
    protected $searchInOrganizations;


    /**
     * @var Programs;
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Programs", mappedBy="account")
     */
    protected $programs;


    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean", name="two_factor_auth_enabled")
     */
    protected $twoFactorAuthEnabled;

    /**
     * Accounts constructor.
     */
    public function __construct()
    {
        $this->status = true;
        $this->accountType = AccountType::DEFAULT;
        $this->users = new ArrayCollection();
        $this->credentials = new ArrayCollection();
        $this->main = false;
        $this->twilioStatus = false;
        $this->forms = new ArrayCollection();
        $this->linkedAccountHistory = new ArrayCollection();
        $this->searchInOrganizations = '[]';
        $this->twoFactorAuthEnabled = false;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getOrganizationName(): ?string
    {
        return $this->organizationName;
    }

    /**
     * @param string $organizationName
     *
     * @return $this
     */
    public function setOrganizationName(string $organizationName = null)
    {
        $this->organizationName = $organizationName;

        return $this;
    }

    /**
     * @return string
     */
    public function getSystemId(): ?string
    {
        return $this->systemId;
    }

    /**
     * @param string $systemId
     *
     * @return $this
     */
    public function setSystemId(string $systemId)
    {
        $this->systemId = $systemId;

        return $this;
    }

    /**
     * @return string
     */
    public function getAccountType(): ?string
    {
        return $this->accountType;
    }

    /**
     * @param string $accountType
     *
     * @return $this
     */
    public function setAccountType(string $accountType)
    {
        $this->accountType = $accountType;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getActivationDate(): ?\DateTime
    {
        return $this->activationDate;
    }

    /**
     * @param \DateTime $activationDate
     *
     * @return $this
     */
    public function setActivationDate(\DateTime $activationDate = null)
    {
        $this->activationDate = $activationDate;

        return $this;
    }

    /**
     * @return string
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param string $status
     *
     * @return $this
     */
    public function setStatus(string $status = null)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return AccountsData
     */
    public function getData(): ?AccountsData
    {
        return $this->data;
    }

    /**
     * @param AccountsData $data
     *
     * @return $this
     */
    public function setData(AccountsData $data = null)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param \App\Entity\Users $user
     *
     * @return $this
     */
    public function addUser(Users $user)
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }

        return $this;
    }

    /**
     * @param \App\Entity\Users $user
     *
     * @return $this
     */
    public function removeUser(Users $user)
    {
        if (!$this->users->contains($user)) {
            $this->users->removeElement($user);
        }

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * @return ArrayCollection
     */
    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * @return bool
     */
    public function isMain(): bool
    {
        return $this->main;
    }

    /**
     * @return string
     */
    public function getTwilioPhone(): ?string
    {
        return $this->twilioPhone;
    }

    /**
     * @param string $twilioPhone
     *
     * @return $this
     */
    public function setTwilioPhone($twilioPhone = null)
    {
        $this->twilioPhone = $twilioPhone;

        return $this;
    }

    /**
     * @return bool
     */
    public function isTwilioStatus()
    {
        return $this->twilioStatus ? 1 : 0;
    }

    /**
     * @param bool $twilioStatus
     *
     * @return $this
     */
    public function setTwilioStatus($twilioStatus)
    {
        $this->twilioStatus = $twilioStatus ? 1 : 0;

        return $this;
    }

    public function getForms(): Collection
    {
        return $this->forms;
    }

    public function addForm(Forms $form): self
    {
        if (!$this->forms->contains($form)) {
            $this->forms[] = $form;
            $form->addTag($this);
        }
        return $this;
    }

    public function removeForm(Forms $form): self
    {
        if ($this->forms->contains($form)) {
            $this->forms->removeElement($form);
            $form->removeTag($this);
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getParticipantType(): ?int
    {
        return $this->participantType;
    }

    /**
     * @param int $participantType
     */
    public function setParticipantType(int $participantType): void
    {
        $this->participantType = $participantType;
    }

    /**
     * @return Collection
     */
    public function getChildrenAccounts(): Collection
    {
        return $this->childrenAccounts;
    }

    /**
     * @param Collection $childrenAccounts
     */
    public function setChildrenAccounts(Collection $childrenAccounts): void
    {
        $this->childrenAccounts = $childrenAccounts;
    }

    /**
     * @return mixed
     */
    public function getParentAccount()
    {
        return $this->parentAccount;
    }

    /**
     * @param mixed $parentAccount
     */
    public function setParentAccount($parentAccount): void
    {
        $this->parentAccount = $parentAccount;
    }

    /**
     * @return ArrayCollection
     */
    public function getLinkedAccountHistory(): Collection
    {
        return $this->linkedAccountHistory;
    }


    public function addLinkedAccountHistory(LinkedAccountHistory $linkedAccountHistory): self
    {
        if (!$this->linkedAccountHistory->contains($linkedAccountHistory)) {
            $this->linkedAccountHistory[] = $linkedAccountHistory;
        }
        return $this;
    }

    public function removeAccount(LinkedAccountHistory $linkedAccountHistory): self
    {
        if ($this->linkedAccountHistory->contains($linkedAccountHistory)) {
            $this->linkedAccountHistory->removeElement($linkedAccountHistory);
        }
        return $this;
    }

    public function clearAccounts(): self
    {
        $this->linkedAccountHistory->clear();
        return $this;
    }

    /**
     * @return bool
     */
    public function isHIPAARegulated()
    {
        return $this->HIPAARegulated;
    }

    /**
     * @param bool $HIPAARegulated
     */
    public function setHIPAARegulated(bool $HIPAARegulated): void
    {
        $this->HIPAARegulated = $HIPAARegulated;
    }

    /**
     * Set main.
     *
     * @param bool $main
     *
     * @return Accounts
     */
    public function setMain($main)
    {
        $this->main = $main;

        return $this;
    }

    /**
     * Get main.
     *
     * @return bool
     */
    public function getMain()
    {
        return $this->main;
    }

    /**
     * Get twilioStatus.
     *
     * @return bool
     */
    public function getTwilioStatus()
    {
        return $this->twilioStatus;
    }

    /**
     * Get hIPAARegulated.
     *
     * @return bool
     */
    public function getHIPAARegulated()
    {
        return $this->HIPAARegulated;
    }

    /**
     * Set searchInOrganizations.
     *
     * @param string $searchInOrganizations
     *
     * @return Accounts
     */
    public function setSearchInOrganizations($searchInOrganizations)
    {
        $this->searchInOrganizations = $searchInOrganizations;

        return $this;
    }

    /**
     * Get searchInOrganizations.
     *
     * @return string
     */
    public function getSearchInOrganizations()
    {
        return $this->searchInOrganizations;
    }

    /**
     * Add credential.
     *
     * @param \App\Entity\Credentials $credential
     *
     * @return Accounts
     */
    public function addCredential(\App\Entity\Credentials $credential)
    {
        $this->credentials[] = $credential;

        return $this;
    }

    /**
     * Remove credential.
     *
     * @param \App\Entity\Credentials $credential
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCredential(\App\Entity\Credentials $credential)
    {
        return $this->credentials->removeElement($credential);
    }

    /**
     * Add childrenAccount.
     *
     * @param \App\Entity\Accounts $childrenAccount
     *
     * @return Accounts
     */
    public function addChildrenAccount(\App\Entity\Accounts $childrenAccount)
    {
        $this->childrenAccounts[] = $childrenAccount;

        return $this;
    }

    /**
     * Remove childrenAccount.
     *
     * @param \App\Entity\Accounts $childrenAccount
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeChildrenAccount(\App\Entity\Accounts $childrenAccount)
    {
        return $this->childrenAccounts->removeElement($childrenAccount);
    }

    /**
     * Remove linkedAccountHistory.
     *
     * @param \App\Entity\LinkedAccountHistory $linkedAccountHistory
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeLinkedAccountHistory(\App\Entity\LinkedAccountHistory $linkedAccountHistory)
    {
        return $this->linkedAccountHistory->removeElement($linkedAccountHistory);
    }

    /**
     * Add program.
     *
     * @param \App\Entity\Programs $program
     *
     * @return Accounts
     */
    public function addProgram(\App\Entity\Programs $program)
    {
        $this->programs[] = $program;

        return $this;
    }

    /**
     * Remove program.
     *
     * @param \App\Entity\Programs $program
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeProgram(\App\Entity\Programs $program)
    {
        return $this->programs->removeElement($program);
    }

    /**
     * Get programs.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPrograms()
    {
        return $this->programs;
    }

    public function getProfileModuleKey()
    {
        if ($this->getParticipantType() == ParticipantType::MEMBER) {
            return 'members_profile';
        }

        return 'participants_profile';
    }

    public function isTwoFactorAuthEnabled(): bool
    {
        return $this->twoFactorAuthEnabled;
    }

    public function setTwoFactorAuthEnabled(bool $twoFactorAuthEnabled): void
    {
        $this->twoFactorAuthEnabled = $twoFactorAuthEnabled;
    }
}
