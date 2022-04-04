<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * FormsData
 *
 * @ORM\Table(name="forms_data")
 * @ORM\Entity(repositoryClass="App\Repository\FormsDataRepository")
 */
class FormsData
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")Fse
     */
    private $id;

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
     * @var \App\Entity\Forms
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Forms")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="form_id", referencedColumnName="id")
     * })
     */
    protected $form;

    /**
     * @var int|null
     *
     * @ORM\Column(name="element_id", type="bigint", nullable=true)
     */
    private $element_id;

    /**
     * @var \App\Entity\Users
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="creator_id", referencedColumnName="id")
     * })
     */
    private $creator;

    /**
     * @var \App\Entity\Users
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="editor_id", referencedColumnName="id")
     * })
     */
    private $editor;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_date",type="datetime")
     */
    private $created_date;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_date",type="datetime")
     */
    private $updated_date;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\FormsValues", mappedBy="data", cascade={"remove"})
     */
    private $values;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(name="manager_id", referencedColumnName="id", nullable=true)
     */
    protected $manager;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(name="secondary_manager_id", referencedColumnName="id", nullable=true)
     */
    protected $secondaryManager;

    /**
     * @var Assignments
     * @ORM\ManyToOne(targetEntity="Assignments")
     * @ORM\JoinColumn(name="assignment_id", referencedColumnName="id", nullable=true)
     */
    protected $assignment;

    /**
     * @var Accounts
     *
     * @ORM\ManyToOne(targetEntity="Accounts")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", nullable=true)
     */
    protected $account_id;

    /**
     * @var null|SharedForm
     *
     * @ORM\OneToOne(targetEntity="App\Entity\SharedForm", mappedBy="formData", cascade={"remove"})
     */
    protected $sharedForm;

    /**
     * FormsData constructor.
     */
    public function __construct()
    {
        $this->values = new ArrayCollection();
    }

    /**
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Modules
     */
    public function getModule(): Modules
    {
        return $this->module;
    }

    /**
     * @param Modules $module
     */
    public function setModule(Modules $module)
    {
        $this->module = $module;
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
     * @param int $element_id
     */
    public function setElementId(?int $element_id)
    {
        $this->element_id = $element_id;
    }

    /**
     * Get elementId
     *
     * @return int|null
     */
    public function getElementId(): ?int
    {
        return $this->element_id;
    }

    /**
    * @return Users
    */
    public function getCreator(): ?Users
    {
        return $this->creator;
    }

    /**
     * @param Users $creator
     */
    public function setCreator(Users $creator)
    {
        $this->creator = $creator;
    }

    /**
     * @return Users
     */
    public function getEditor(): ?Users
    {
        return $this->editor;
    }

    /**
     * @param Users $editor
     */
    public function setEditor(Users $editor)
    {
        $this->editor = $editor;
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
     * @return \DateTime
     */
    public function getUpdatedDate(): \DateTime
    {
        return $this->updated_date;
    }

    /**
     * @param \DateTime $updated_date
     */
    public function setUpdatedDate(\DateTime $updated_date)
    {
        $this->updated_date = $updated_date;
    }

    /**
     * @return ArrayCollection|Collection
     */
    public function getValues(): Collection
    {
        return $this->values;
    }

    /**
     * @param FormsValues $value
     */
    public function addValue(FormsValues $value)
    {
        if (!$this->values->contains($value)) {
            $this->values->add($value);
        }
    }

    /**
     * @return Users
     */
    public function getManager(): ?Users
    {
        return $this->manager;
    }

    /**
     * @param Users $manager
     */
    public function setManager(Users $manager = null)
    {
        $this->manager = $manager;
    }

    /**
     * @return Users
     */
    public function getSecondaryManager(): ?Users
    {
        return $this->secondaryManager;
    }

    /**
     * @param Users $manager
     */
    public function setSecondaryManager(Users $manager = null)
    {
        $this->secondaryManager = $manager;
    }

    /**
     * @return Assignments
     */
    public function getAssignment(): ?Assignments
    {
        return $this->assignment;
    }

    /**
     * @param mixed $assignment
     */
    public function setAssignment(Assignments $assignment = null)
    {
        $this->assignment = $assignment;
    }

    /**
     * @return Accounts
     */
    public function getAccount(): ?Accounts
    {
        return $this->account_id;
    }

    /**
     * @param Accounts $account_id
     */
    public function setAccount(Accounts $account_id): void
    {
        $this->account_id = $account_id;
    }

    /**
     * @return null|SharedForm
     */
    public function getSharedForm(): ?SharedForm
    {
        return $this->sharedForm;
    }

}
