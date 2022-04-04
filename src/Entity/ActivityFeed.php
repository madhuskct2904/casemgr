<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class ActivityFeed
 * @package App\Entity
 *
 * @ORM\Table(name="activity_feeds")
 * @ORM\Entity(repositoryClass="App\Repository\ActivityFeedRepository")
 */
class ActivityFeed extends Entity
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
     * @ORM\Column(name="template", type="string", nullable=false)
     */
    protected $template;

    /**
     * @var Users
     *
     * @ORM\ManyToOne(targetEntity="Users", inversedBy="activityFeedsP")
     * @ORM\JoinColumn(name="participant_id", referencedColumnName="id")
     */
    protected $participant;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", nullable=true)
     */
    protected $title;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at",type="datetime", nullable=false)
     */
    protected $createdAt;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $templateId;

    /**
     * @var Accounts
     *
     * @ORM\ManyToOne(targetEntity="Accounts")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id")
     */
    protected $account;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected $details;

    /**
     * ActivityFeed constructor.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime();
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
    public function getTemplate(): ?string
    {
        return $this->template;
    }

    /**
     * @param string $template
     * @return $this
     */
    public function setTemplate(string $template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getParticipant(): ?Users
    {
        return $this->participant;
    }

    /**
     * @param mixed $participant
     * @return $this
     */
    public function setParticipant(Users $participant = null)
    {
        $this->participant = $participant;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title = null)
    {
        $this->title = $title;

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
     * @return int
     */
    public function getTemplateId(): ?int
    {
        return $this->templateId;
    }

    /**
     * @param int $templateId
     * @return $this
     */
    public function setTemplateId($templateId = null)
    {
        $this->templateId = $templateId;

        return $this;
    }

    /**
     * @return Accounts|null
     */
    public function getAccount(): ?Accounts
    {
        return $this->account;
    }

    /**
     * @param Accounts|null $account
     *
     * @return ActivityFeed
     */
    public function setAccount(Accounts $account = null)
    {
        $this->account = $account;

        return $this;
    }

    /**
     * @return array
     */
    public function getDetails(): array
    {
        return (is_null($this->details) === false) ? json_decode($this->details, true) : [];
    }

    /**
     * @param array $details
     *
     * @return ActivityFeed
     */
    public function setDetails(array $details)
    {
        $this->details = json_encode($details);

        return $this;
    }
}
