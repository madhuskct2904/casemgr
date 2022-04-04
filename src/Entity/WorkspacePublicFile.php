<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Documents\Account;

/**
 * WorkspaceSharedFile
 *
 * @ORM\Table(name="workspace_public_files")
 * @ORM\Entity(repositoryClass="App\Repository\WorkspacePublicFileRepository")
 */
class WorkspacePublicFile
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
     * @var string
     *
     * @ORM\Column(name="original_filename", type="string", length=255)
     */
    private $originalFilename;

    /**
     * @var string
     *
     * @ORM\Column(name="server_filename", type="string", length=255)
     */
    private $serverFilename;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255)
     */
    private $description;


    /**
     * @var string
     *
     * @ORM\Column(name="public_url", type="string", length=255)
     */
    private $publicUrl;

    /**
     * @var int|null
     *
     * @ORM\ManyToOne(targetEntity="Accounts", inversedBy="workspaceSharedFiles")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $account;

    /**
     * @var int|null
     *
     * @ORM\ManyToOne(targetEntity="Users", inversedBy="workspaceSharedFiles")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $user;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at",type="datetime", nullable=false)
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="updated_at",type="datetime", nullable=true)
     */
    protected $updatedAt;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="deleted_at",type="datetime", nullable=true)
     */
    protected $deletedAt;

    /**
     * WorkspaceSharedFile constructor.
     */
    public function __construct()
    {
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
     * Set originalFilename.
     *
     * @param string $originalFilename
     *
     * @return WorkspaceSharedFile
     */
    public function setOriginalFilename($originalFilename)
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    /**
     * Get originalFilename.
     *
     * @return string
     */
    public function getOriginalFilename()
    {
        return $this->originalFilename;
    }

    /**
     * Set serverFilename.
     *
     * @param string $serverFilename
     *
     * @return WorkspaceSharedFile
     */
    public function setServerFilename($serverFilename)
    {
        $this->serverFilename = $serverFilename;

        return $this;
    }

    /**
     * Get serverFilename.
     *
     * @return string
     */
    public function getServerFilename()
    {
        return $this->serverFilename;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return WorkspaceSharedFile
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }

    /**
     * @param string $publicUrl
     */
    public function setPublicUrl(string $publicUrl): void
    {
        $this->publicUrl = $publicUrl;
    }

    /**
     * Set account.
     *
     * @param Accounts|null $account
     *
     * @return WorkspaceSharedFile
     */
    public function setAccount(?Accounts $account = null)
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Get account.
     *
     * @return int|null
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * Set user.
     *
     * @param Users|null $user
     *
     * @return WorkspaceSharedFile
     */
    public function setUser(?Users $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return int|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(?\DateTime $updatedAt = null): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return \DateTime|null
     */
    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    /**
     * @param \DateTime|null $deletedAt
     */
    public function setDeletedAt(?\DateTime $deletedAt = null): void
    {
        $this->deletedAt = $deletedAt;
    }
}
