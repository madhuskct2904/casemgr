<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Accounts
 * @package App\Entity
 *
 * @ORM\Table(name="reports")
 * @ORM\Entity(repositoryClass="App\Repository\ReportsRepository")
 */
class Reports extends Entity
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
     * @ORM\Column(name="name",type="string", length=255)
     */
    protected $name;

    /**
     * @ORM\Column(name="description", type="string")
     */
    protected $description;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_date",type="datetime")
     */
    protected $created_date;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified_date",type="datetime",nullable=true)
     */
    protected $modifiedDate;

    /**
     * @var \App\Entity\Users
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     * })
     */
    protected $user;

    /**
     * @ORM\Column(name="status", type="string", columnDefinition="enum('0', '1')")
     */
    protected $status = '1';

    /**
     * @ORM\Column(name="type", type="string", columnDefinition="enum('report', 'template')")
     */
    protected $type = 'report';

    /**
     * @ORM\Column(name="data",type="text")
     */
    protected $data;

    /**
     * @var Accounts
     *
     * @ORM\ManyToOne(targetEntity="Accounts")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", nullable=true)
     */
    protected $account;

    /**
     * Report mode - 1. single organization, 2. multiple organizations
     *
     * @ORM\Column(name="mode", type="smallint")
     */
    protected $mode;

    /**
     * JSON with accounts ID's for multiple-organizations reports
     *
     * @ORM\Column(name="accounts", type="text")
     */
    protected $accounts;

    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\ReportsForms", mappedBy="report", orphanRemoval=true, cascade={"remove"})
     * @ORM\JoinTable(name="reports_forms",
     *      joinColumns={@ORM\JoinColumn(name="form_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="report_id", referencedColumnName="id", unique=true)}
     * )
     */
    protected $forms;

    /**
     * @var int
     *
     * @ORM\Column(name="results_count", type="integer")
     *
     **/
    protected $resultsCount;

    /**
     * @ORM\Column(name="date_format" ,type="string", length=15)
     */
    protected $dateFormat;


    /**
     * @var \App\Entity\ReportFolder
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\ReportFolder")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="report_folder_id", referencedColumnName="id")
     * })
     */
    protected $folder;

    /**
     * @ORM\OneToOne(targetEntity="Reports")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parentId;


    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\ReportSummary", mappedBy="report", orphanRemoval=true, cascade={"remove"})
     */
    protected $summaries;


    /**
     * Reports constructor.
     */
    public function __construct()
    {
        $this->forms = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedDate(): \DateTime
    {
        return $this->created_date;
    }

    /**
     * @param \DateTime $created_date
     */
    public function setCreatedDate(\DateTime $created_date)
    {
        $this->created_date = $created_date;
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
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus(int $status)
    {
        $this->status = (string)$status;
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
     * @return Accounts
     */
    public function getAccount(): ?Accounts
    {
        return $this->account;
    }

    /**
     * @param Accounts $account
     */
    public function setAccount(Accounts $account = null)
    {
        $this->account = $account;
    }

    /**
     * @return mixed
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param mixed $mode
     */
    public function setMode($mode): void
    {
        $this->mode = $mode;
    }

    /**
     * @return mixed
     */
    public function getAccounts()
    {
        return $this->accounts;
    }

    /**
     * @param mixed $accounts
     */
    public function setAccounts(string $accounts): void
    {
        $this->accounts = $accounts;
    }


    /**
     * @return int
     */
    public function getResultsCount(): ?int
    {
        return $this->resultsCount;
    }

    /**
     * @param int|null $resultsCount
     */
    public function setResultsCount(?int $resultsCount)
    {
        $this->resultsCount = $resultsCount;
    }

    /**
     * @return ArrayCollection
     */
    public function getForms()
    {
        return $this->forms;
    }

    /**
     * @param Forms $form
     */
    public function addForm(Forms $form)
    {
        if (!$this->forms->contains($form)) {
            $this->forms->add($form);
        }
    }

    public function clearForms()
    {
        $this->forms->clear();
        return $this;
    }

    /**
     * Set folder.
     *
     * @param \App\Entity\ReportFolder|null $folder
     *
     * @return Reports
     */
    public function setFolder(\App\Entity\ReportFolder $folder = null)
    {
        $this->folder = $folder;

        return $this;
    }

    /**
     * Get folder.
     *
     * @return \App\Entity\ReportFolder|null
     */
    public function getFolder()
    {
        return $this->folder;
    }


    /**
     * Set modifiedDate.
     *
     * @param \DateTime|null $modifiedDate
     *
     * @return Reports
     */
    public function setModifiedDate($modifiedDate = null)
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }

    /**
     * Get modifiedDate.
     *
     * @return \DateTime|null
     */
    public function getModifiedDate()
    {
        return $this->modifiedDate;
    }

    /**
     * @return mixed
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * @param mixed $dateFormat
     */
    public function setDateFormat($dateFormat): void
    {
        $this->dateFormat = $dateFormat;
    }

    /**
     * Remove form.
     *
     * @param \App\Entity\ReportsForms $form
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeForm(\App\Entity\ReportsForms $form)
    {
        return $this->forms->removeElement($form);
    }

    /**
     * Set parentId.
     *
     * @param \App\Entity\Reports|null $parentId
     *
     * @return Reports
     */
    public function setParentId(\App\Entity\Reports $parentId = null)
    {
        $this->parentId = $parentId;

        return $this;
    }

    /**
     * Get parentId.
     *
     * @return \App\Entity\Reports|null
     */
    public function getParentId()
    {
        return $this->parentId;
    }

    /**
     * @return Collection
     */
    public function getSummaries(): Collection
    {
        return $this->summaries;
    }

    /**
     * @param Collection $summaries
     */
    public function setSummaries(Collection $summaries): void
    {
        $this->summaries = $summaries;
    }



}
