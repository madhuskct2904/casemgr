<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Imports
 * @package App\Entity
 *
 * @ORM\Table(name="imports")
 * @ORM\Entity(repositoryClass="App\Repository\ImportsRepository")
 */
class Imports extends Entity
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
     * @ORM\Column(name="file", type="string", nullable=false)
     */
    protected $file;

    /**
     * @var string
     *
     * @ORM\Column(name="original_filename", type="string", nullable=false)
     */
    protected $originalFile;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=31)
     */
    protected $context;

    /**
     * @var \App\Entity\Forms
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Forms")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="form_id", referencedColumnName="id")
     * })
     */
    protected $form;

    /**
     * @var \App\Entity\Accounts
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Accounts")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="account_id", referencedColumnName="id")
     * })
     */
    protected $account;

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
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $createdDate;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    protected $status;

    /**
     * @var \App\Entity\Accounts
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Accounts")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="form_account_id", referencedColumnName="id")
     * })
     */
    protected $formAccount;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $keyField;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text")
     */
    protected $map = '[]';

    /**
     * @var string|null
     *
     * @ORM\Column(name="csv_header", type="text")
     */
    protected $csvHeader = '[]';

    /**
     * @var string|null
     *
     * @ORM\Column(name="success_rows", type="text")
     */
    protected $successRows = '[]';

    /**
     * @var string|null
     *
     * @ORM\Column(name="failed_rows", type="text")
     */
    protected $failedRows = '[]';

    /**
     * @var string|null
     *
     * @ORM\Column(name="ignore_rows", type="text")
     */
    protected $ignoreRows = '[]';

    /**
     * @var int
     *
     * @ORM\Column(name="total_rows", type="integer", nullable=false)
     */
    protected $totalRows = 0;

    /**
     * @var int|null
     *
     * @ORM\Column(name="last_processed_row", type="integer", nullable=true)
     */
    protected $lastProcessedRow;

    /**
     * Imports constructor.
     */
    public function __construct()
    {
        $this->status = 'pending';
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param string $file
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * @return string
     */
    public function getOriginalFile()
    {
        return $this->originalFile;
    }

    /**
     * @param string $originalFile
     */
    public function setOriginalFile($originalFile)
    {
        $this->originalFile = $originalFile;
    }

    /**
     * @return string
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * @param string $context
     */
    public function setContext(string $context): void
    {
        $this->context = $context;
    }

    /**
     * @return Forms
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @param Forms $form
     */
    public function setForm($form)
    {
        $this->form = $form;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @param \DateTime $createdDate
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
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

    /**
     * @return Users
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param Users $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return Accounts
     */
    public function getFormAccount()
    {
        return $this->formAccount;
    }

    /**
     * @param Accounts $formAccount
     */
    public function setFormAccount($formAccount)
    {
        $this->formAccount = $formAccount;
    }

    /**
     * @return string|null
     */
    public function getKeyField(): array
    {
        return json_decode($this->keyField, true);
    }

    /**
     * @param string $keyField
     */
    public function setKeyField(array $keyField): void
    {
        $this->keyField = json_encode($keyField);
    }

    /**
     * @return array|null
     */
    public function getMap(): ?array
    {
        return json_decode($this->map, true);
    }

    /**
     * @param array $map
     */
    public function setMap(array $map): void
    {
        $this->map = json_encode($map);
    }

    /**
     * @return string|null
     */
    public function getCsvHeader(): array
    {
        return json_decode($this->csvHeader, true);
    }

    /**
     * @param string|null $csvHeader
     */
    public function setCsvHeader(array $csvHeader): void
    {
        $this->csvHeader = json_encode($csvHeader);
    }

    /**
     * @return string|null
     */
    public function getSuccessRows(): array
    {
        return json_decode($this->successRows, true);
    }

    /**
     * @param string|null $successRows
     */
    public function setSuccessRows(array $successRows): void
    {
        $this->successRows = json_encode($successRows);
    }

    /**
     * @return string|null
     */
    public function getFailedRows(): array
    {
        return json_decode($this->failedRows, true);
    }

    /**
     * @param string|null $failedRows
     */
    public function setFailedRows(array $failedRows): void
    {
        $this->failedRows = json_encode($failedRows);
    }

    /**
     * @return array
     */
    public function getIgnoreRows(): array
    {
        return json_decode($this->ignoreRows, true);
    }

    /**
     * @param array $ignoreRows
     */
    public function setIgnoreRows(array $ignoreRows): void
    {
        $this->ignoreRows = json_encode($ignoreRows);
    }

    /**
     * @return int
     */
    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    /**
     * @param int $totalRows
     */
    public function setTotalRows(int $totalRows): void
    {
        $this->totalRows = $totalRows;
    }

    /**
     * @return int|null
     */
    public function getLastProcessedRow(): ?int
    {
        return $this->lastProcessedRow;
    }

    /**
     * @param int|null $lastProcessedRow
     */
    public function setLastProcessedRow(?int $lastProcessedRow): void
    {
        $this->lastProcessedRow = $lastProcessedRow;
    }

}
