<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="forms")
 * @ORM\Entity(repositoryClass="App\Repository\FormsRepository")
 */
class Forms extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="name",type="string", length=255)
     */
    protected $name;

    /**
     * @ORM\Column(name="description", type="string")
     */
    protected $description;

    /**
     * @ORM\Column(name="data",type="text")
     */
    protected $data;

    /**
     * @ORM\Column(name="type", type="string", columnDefinition="enum('form', 'template')")
     */
    protected $type;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="created_date",type="datetime")
     */
    protected $created_date;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="last_action_date",type="datetime", nullable=true)
     */
    protected $last_action_date;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     * })
     */
    protected $user;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="last_action_user", referencedColumnName="id")
     * })
     */
    protected $last_action_user;

    /**
     * @var Collection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\FormsHistory", mappedBy="form")
     * @ORM\OrderBy({"date" = "DESC"})
     */
    protected $forms_history;

    /**
     * @ORM\Column(name="status", type="boolean", nullable=false)
     */
    private $status = true;

    /**
     * @ORM\Column(name="publish", type="boolean", nullable=false)
     */
    private $publish = true;

    /**
     * @ORM\Column(name="conditionals",type="text")
     */
    protected $conditionals;

    /**
     * @ORM\Column(name="system_conditionals",type="text")
     */
    protected $systemConditionals;

    /**
     * @ORM\Column(name="update_conditionals", type="text", nullable=true)
     */
    protected ?string $updateConditionals;

    /**
     * @ORM\Column(name="calculations",type="text")
     */
    protected $calculations;

    /**
     * @ORM\Column(name="hide_values",type="text", nullable=true)
     */
    protected $hideValues;

    /**
     * @ORM\Column(name="columns_map",type="text")
     */
    protected $columns_map;

    /**
     * @ORM\Column(name="custom_columns",type="text", nullable=true)
     */
    protected $custom_columns;

    /**
     * @var \App\Entity\Modules
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Modules")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="module_id", referencedColumnName="id")
     * })
     */
    protected $module;

    /**
     * @var Accounts
     * @ORM\ManyToMany(targetEntity="Accounts", inversedBy="accounts")
     */
    protected $accounts;

    /**
     * @ORM\Column(name="access_level",type="smallint", nullable=true)
     */
    protected $accessLevel;


    /**
     * @ORM\Column(name="multiple_entries", type="boolean")
     */
    protected $multiple_entries;


    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Programs", mappedBy="forms")
     */
    protected $programs;

    /**
     * @ORM\Column(name="share_uid",type="string", length=63, nullable=true)
     */
    protected $shareUid;


    /**
     * @ORM\Column(name="share_with_participant",type="boolean", nullable=false)
     */
    protected $shareWithParticipant = false;

    /**
     * @ORM\Column(name="captcha_enabled",type="boolean", nullable=false)
     */
    protected $captchaEnabled = false;


    /**
     * @ORM\Column(name="extra_validation_rules",type="text")
     */
    protected $extraValidationRules = '[]';

    /**
     * @ORM\Column(name="has_shared_fields",type="boolean", nullable=false)
     */
    protected $hasSharedFields = false;

    /**
     * Forms constructor.
     */
    public function __construct()
    {
        $this->accounts = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return (int)$this->id;
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
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param string $description
     *
     * @return $this
     */
    public function setDescription(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @param string $data
     */
    public function setData(string $data)
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type)
    {
        $this->type = $type;
    }

    /**
     * @return DateTime
     */
    public function getCreatedDate(): DateTime
    {
        return $this->created_date;
    }

    /**
     * @param DateTime $created_date
     */
    public function setCreatedDate(DateTime $created_date)
    {
        $this->created_date = $created_date;
    }

    /**
     * @return DateTime|null
     */
    public function getLastActionDate(): ?DateTime
    {
        return $this->last_action_date;
    }

    /**
     * @param DateTime $last_action_date
     */
    public function setLastActionDate(DateTime $last_action_date)
    {
        $this->last_action_date = $last_action_date;
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
     * @return Collection
     */
    public function getFormsHistory(): Collection
    {
        return $this->forms_history;
    }

    /**
     * @param Collection $forms_history
     */
    public function setFormsHistory(Collection $forms_history)
    {
        $this->forms_history = $forms_history;
    }

    /**
     * @return Users
     */
    public function getLastActionUser(): ?Users
    {
        return $this->last_action_user;
    }

    /**
     * @param Users $last_action_user
     */
    public function setLastActionUser(Users $last_action_user)
    {
        $this->last_action_user = $last_action_user;
    }

    /**
     * @return bool
     */
    public function isDisabled(): bool
    {
        return $this->status;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->status;
    }

    /**
     * @return boolean
     */
    public function getStatus(): bool
    {
        return $this->status;
    }

    /**
     * @param boolean $status
     */
    public function setStatus(bool $status)
    {
        $this->status = $status;
    }

    /**
     * @return boolean
     */
    public function getPublish(): bool
    {
        return $this->publish;
    }

    /**
     * @param boolean $publish
     */
    public function setPublish(bool $publish)
    {
        $this->publish = $publish;
    }

    /**
     * @return mixed
     */
    public function getConditionals()
    {
        return $this->conditionals;
    }

    /**
     * @param mixed $systemConditionals
     */
    public function setSystemConditionals($systemConditionals)
    {
        $this->systemConditionals = $systemConditionals;
    }

    /**
     * @return mixed
     */
    public function getSystemConditionals()
    {
        return $this->systemConditionals;
    }

    /**
     * @param mixed $conditionals
     */
    public function setConditionals($conditionals)
    {
        $this->conditionals = $conditionals;
    }


    /**
     * @return mixed
     */
    public function getCalculations()
    {
        return $this->calculations;
    }

    /**
     * @param mixed $calculations
     */
    public function setCalculations($calculations)
    {
        $this->calculations = $calculations;
    }

    /**
     * @return string
     */
    public function getColumnsMap()
    {
        return $this->columns_map;
    }

    /**
     * @param string $map
     */
    public function setColumnsMap($map)
    {
        $this->columns_map = $map;
    }

    /**
     * @return string
     */
    public function getCustomColumns()
    {
        return $this->custom_columns;
    }

    /**
     * @param string $columns
     */
    public function setCustomColumns($columns)
    {
        $this->custom_columns = $columns;
    }

    /**
     * @return Modules
     */
    public function getModule(): ?Modules
    {
        return $this->module;
    }

    /**
     * @param Modules $module
     */
    public function setModule(?Modules $module)
    {
        $this->module = $module;
    }

    /**
     * @param $accessLevel
     */
    public function setAccessLevel($accessLevel)
    {
        $this->accessLevel = $accessLevel;
    }

    /**
     * @return mixed
     */
    public function getAccessLevel(): ?int
    {
        return $this->accessLevel;
    }

    public function getAccounts(): Collection
    {
        return $this->accounts;
    }

    public function addAccount(Accounts $account): self
    {
        if (!$this->accounts->contains($account)) {
            $this->accounts[] = $account;
        }
        return $this;
    }

    public function removeAccount(Accounts $account): self
    {
        if ($this->accounts->contains($account)) {
            $this->accounts->removeElement($account);
        }
        return $this;
    }

    public function clearAccounts(): self
    {
        $this->accounts->clear();
        return $this;
    }

    public function getMultipleEntries(): bool
    {
        return $this->multiple_entries;
    }

    public function setMultipleEntries(bool $multiple_entries): void
    {
        $this->multiple_entries = $multiple_entries;
    }

    /**
     * @return mixed
     */
    public function getHideValues()
    {
        return $this->hideValues;
    }

    /**
     * @param mixed $hideValues
     */
    public function setHideValues($hideValues): void
    {
        $this->hideValues = $hideValues;
    }

    /**
     * Add formsHistory.
     *
     * @param FormsHistory $formsHistory
     *
     * @return Forms
     */
    public function addFormsHistory(FormsHistory $formsHistory)
    {
        $this->forms_history[] = $formsHistory;

        return $this;
    }

    /**
     * Remove formsHistory.
     *
     * @param FormsHistory $formsHistory
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeFormsHistory(FormsHistory $formsHistory)
    {
        return $this->forms_history->removeElement($formsHistory);
    }

    /**
     * Add program.
     *
     * @param Programs $program
     *
     * @return Forms
     */
    public function addProgram(Programs $program)
    {
        $this->programs[] = $program;

        return $this;
    }

    /**
     * Remove program.
     *
     * @param Programs $program
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeProgram(Programs $program)
    {
        return $this->programs->removeElement($program);
    }

    /**
     * Get programs.
     *
     * @return Collection
     */
    public function getPrograms()
    {
        return $this->programs;
    }

    /**
     * @return mixed
     */
    public function getshareUid()
    {
        return $this->shareUid;
    }

    /**
     * @param mixed $shareUid
     */
    public function setshareUid($shareUid): void
    {
        $this->shareUid = $shareUid;
    }

    /**
     * @return boolean
     */
    public function isSharedWithParticipant(): bool
    {
        return $this->shareWithParticipant;
    }

    /**
     * @param boolean $shareWithParticipant
     */
    public function setShareWithParticipant(bool $shareWithParticipant): Forms
    {
        $this->shareWithParticipant = $shareWithParticipant;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasSharedFields(): bool
    {
        return $this->hasSharedFields;
    }

    /**
     * @param bool $hasSharedFields
     */
    public function setHasSharedFields(bool $hasSharedFields): void
    {
        $this->hasSharedFields = $hasSharedFields;
    }


    /**
     * @return bool
     */
    public function isCaptchaEnabled(): bool
    {
        return $this->captchaEnabled;
    }

    /**
     * @param bool $captchaEnabled
     */
    public function setCaptchaEnabled(bool $captchaEnabled): void
    {
        $this->captchaEnabled = $captchaEnabled;
    }

    /**
     * @return string
     */
    public function getExtraValidationRules(): string
    {
        if ($this->extraValidationRules === '') {
            return '[]';
        }

        return $this->extraValidationRules;
    }

    /**
     * @param string $extraValidationRules
     */
    public function setExtraValidationRules(string $extraValidationRules): void
    {
        if ($extraValidationRules === '') {
            $this->extraValidationRules = '[]';
            return;
        }

        $this->extraValidationRules = $extraValidationRules;
    }

    public function getUpdateConditionals(): ?string
    {
        return $this->updateConditionals;
    }

    public function setUpdateConditionals(?string $updateConditionals): void
    {
        $this->updateConditionals = $updateConditionals;
    }
}
