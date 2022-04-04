<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="forms_history")
 */
class FormsHistory extends Entity
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
     * @ORM\Column(name="data",type="text")
     */
    protected $data;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date",type="datetime")
     */
    protected $date;

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
     * @var \App\Entity\Forms
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Forms")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="form_id", referencedColumnName="id")
     * })
     */
    protected $form;

    /**
     * @ORM\Column(name="conditionals",type="text")
     */
    protected $conditionals;

    /**
     * @return int
     */
    public function getId(): int
    {
        return (int) $this->id;
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
     * @return \DateTime
     */
    public function getDate(): \DateTime
    {
        return $this->date;
    }

    /**
     * @param \DateTime $date
     */
    public function setDate(\DateTime $date)
    {
        $this->date = $date;
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
     * @return Forms
     */
    public function getForm(): Forms
    {
        return $this->form;
    }

    /**
     * @param Forms $form
     */
    public function setForm(Forms $form)
    {
        $this->form = $form;
    }

    /**
     * @return mixed
     */
    public function getConditionals()
    {
        return $this->conditionals;
    }

    /**
     * @param mixed $conditionals
     */
    public function setConditionals($conditionals)
    {
        $this->conditionals = $conditionals;
    }
}
