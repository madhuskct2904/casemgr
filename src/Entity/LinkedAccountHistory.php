<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class LinkedAccountHistory
 * @package App\Entity
 *
 * @ORM\Entity()
 * @ORM\Table(name="linked_accounts_history")
 */
class LinkedAccountHistory extends Entity
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
     * @var \App\Entity\Accounts
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Accounts", inversedBy="linkedAccountHistory")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $account;

    /**
     * @ORM\Column(name="data", type="text")
     */
    protected $data;

    /**
     * @ORM\Column(name="user_data", type="text")
     */
    protected $userData;

    /**
     * @ORM\Column(name="created_date", type="datetime")
     */
    protected $createdDate;

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
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return Accounts
     */
    public function getAccount(): Accounts
    {
        return $this->account;
    }

    /**
     * @param Accounts $account
     */
    public function setAccount(Accounts $account): void
    {
        $this->account = $account;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getUserData()
    {
        return $this->userData;
    }

    /**
     * @param mixed $userData
     */
    public function setUserData($userData): void
    {
        $this->userData = $userData;
    }

    /**
     * @return mixed
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @param mixed $createdDate
     */
    public function setCreatedDate($createdDate): void
    {
        $this->createdDate = $createdDate;
    }
}
