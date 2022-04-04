<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UsersActivityLog
 *
 * @ORM\Table(name="users_auth")
 * @ORM\Entity(repositoryClass="App\Repository\UserAuthRepository")
 */
class UserAuth
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
     * @var \App\Entity\Users
     *
     * @ORM\OneToOne(targetEntity="App\Entity\Users")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="SET NULL")
     *
     */
    private $user;


    /**
     * @var Accounts
     *
     * @ORM\ManyToOne(targetEntity="Accounts")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", nullable=true)
     */
    protected $account;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=8)
     */
    private $code;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime")
     */
    private $updatedAt;

    /**
     * @var string
     *
     * @ORM\Column(name="browser_fingerprint", type="string", length=255)
     */
    private $browserFingerprint;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean", name="email_sent")
     */
    private $emailSent;

    /**
     * @var string
     *
     * @ORM\Column(name="token", type="string", length=63)
     */
    private $token;


    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUser(): Users
    {
        return $this->user;
    }

    public function setUser(Users $user): void
    {
        $this->user = $user;
    }

    public function getAccount(): Accounts
    {
        return $this->account;
    }

    public function setAccount(Accounts $account): void
    {
        $this->account = $account;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updateAt): void
    {
        $this->updatedAt = $updateAt;
    }

    public function getBrowserFingerprint(): string
    {
        return $this->browserFingerprint;
    }

    public function setBrowserFingerprint(string $browserFingerprint): void
    {
        $this->browserFingerprint = $browserFingerprint;
    }

    public function isEmailSent(): bool
    {
        return $this->emailSent;
    }

    public function setEmailSent(bool $emailSent): void
    {
        $this->emailSent = $emailSent;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }
}
