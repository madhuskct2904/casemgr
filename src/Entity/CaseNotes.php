<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class CaseNotes
 * @package App\Entity
 *
 * @ORM\Table(name="case_notes")
 * @ORM\Entity(repositoryClass="App\Repository\CaseNotesRepository")
 */
class CaseNotes extends Entity
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
     * @ORM\Column(type="string", nullable=false)
     * @Assert\NotBlank
     * @Assert\Expression(
     *     "this.validateType()"
     * )
     */
    protected $type;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     * @Assert\NotBlank
     * @Assert\Length(
     *     max = 5000
     * )
     */
    protected $note;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $modifiedAt;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(referencedColumnName="id")
     *
     * @Assert\Valid
     */
    protected $createdBy;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(referencedColumnName="id")
     */
    protected $modifiedBy;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumn(referencedColumnName="id")
     *
     * @Assert\Valid
     */
    protected $manager;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users", inversedBy="caseNotesP")
     * @ORM\JoinColumn(referencedColumnName="id")
     *
     * @Assert\Valid
     */
    protected $participant;

    /**
     * @var Assignments
     *
     * @ORM\ManyToOne(targetEntity="Assignments")
     * @ORM\JoinColumn(name="assignment_id", referencedColumnName="id", nullable=true)
     */
    protected $assignment;

    /**
     * @var boolean
     *
     * @ORM\Column(name="read_only", type="boolean", nullable=false)
     */
    protected $readOnly;

    /**
     * CaseNotes constructor.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->readOnly = false;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * @param string $note
     * @return $this
     */
    public function setNote(string $note)
    {
        $this->note = $note;

        return $this;
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
     * @return $this
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getModifiedAt(): ?\DateTime
    {
        return $this->modifiedAt;
    }

    /**
     * @param \DateTime $modifiedAt
     * @return $this
     */
    public function setModifiedAt(\DateTime $modifiedAt = null)
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    /**
     * @return Users
     */
    public function getCreatedBy(): ?Users
    {
        return $this->createdBy;
    }

    /**
     * @param Users $createdBy
     * @return $this
     */
    public function setCreatedBy(Users $createdBy = null)
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Users
     */
    public function getModifiedBy(): ?Users
    {
        return $this->modifiedBy;
    }

    /**
     * @param Users $modifiedBy
     * @return $this
     */
    public function setModifiedBy(Users $modifiedBy = null)
    {
        $this->modifiedBy = $modifiedBy;

        return $this;
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
     * @return $this
     */
    public function setManager(Users $manager = null)
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * @return Users
     */
    public function getParticipant(): ?Users
    {
        return $this->participant;
    }

    /**
     * @param Users $participant
     * @return $this
     */
    public function setParticipant(Users $participant = null)
    {
        $this->participant = $participant;

        return $this;
    }

    /**
     * @return Assignments
     */
    public function getAssignment(): ?Assignments
    {
        return $this->assignment;
    }

    /**
     * @param Assignments $assignment
     * @return $this
     */
    public function setAssignment(Assignments $assignment = null)
    {
        $this->assignment = $assignment;

        return $this;
    }

    public function validateType()
    {
        return in_array($this->type, ['collateral','email','person','phone','social','text','referral','virtual']);
    }

    /**
     * @return bool
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * @param bool $readOnly
     */
    public function setReadOnly(bool $readOnly): void
    {
        $this->readOnly = $readOnly;
    }

}
