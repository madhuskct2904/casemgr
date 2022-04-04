<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="programs")
 */
class Programs extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var \App\Entity\Users
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Accounts")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="account_id", referencedColumnName="id")
     * })
     */
    protected $account;

    /**
     * @ORM\Column(name="name",type="string", length=255)
     */
    protected $name;

    /**
     * @ORM\Column(name="status",type="smallint")
     */
    protected $status;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="creation_date",type="datetime")
     */
    protected $creationDate;

    /**
     * @ORM\ManyToMany(targetEntity="Forms", inversedBy="programs")
     */
    protected $forms;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->forms = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set name.
     *
     * @param string $name
     *
     * @return Programs
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set status.
     *
     * @param int $status
     *
     * @return Programs
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set creationDate.
     *
     * @param \DateTime $creationDate
     *
     * @return Programs
     */
    public function setCreationDate($creationDate)
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    /**
     * Get creationDate.
     *
     * @return \DateTime
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * Set account.
     *
     * @param \App\Entity\Accounts|null $account
     *
     * @return Programs
     */
    public function setAccount(\App\Entity\Accounts $account = null)
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Get account.
     *
     * @return \App\Entity\Accounts|null
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * Add form.
     *
     * @param \App\Entity\Forms $form
     *
     * @return Programs
     */
    public function addForm(\App\Entity\Forms $form)
    {
        $this->forms[] = $form;

        return $this;
    }

    /**
     * Remove form.
     *
     * @param \App\Entity\Forms $form
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeForm(\App\Entity\Forms $form)
    {
        return $this->forms->removeElement($form);
    }

    /**
     * Get forms.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getForms()
    {
        return $this->forms;
    }
}
