<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class ParticipantDirectoryColumns
 * @package App\Entity
 *
 * @ORM\Table(name="participant_directory_columns")
 * @ORM\Entity(repositoryClass="App\Repository\ParticipantDirectoryColumnsRepository")
 */
class ParticipantDirectoryColumns extends Entity
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
     * @var Accounts
     *
     * @ORM\ManyToOne(targetEntity="Accounts")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id")
     */
    protected $account;

    /**
     * @ORM\Column(name="columns",type="text", nullable=true)
     */
    protected $columns;


    /**
     * @ORM\Column(name="context",type="string", nullable=true)
     */
    protected $context;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
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
     * @return string
     */
    public function getColumns(): string
    {
        return $this->columns;
    }

    /**
     * @param string $columns
     */
    public function setColumns(string $columns): void
    {
        $this->columns = $columns;
    }

    /**
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param mixed $context
     */
    public function setContext($context): void
    {
        $this->context = $context;
    }
}
