<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SharedFormSmsMessage
 *
 * @ORM\Table(name="shared_form_sms_message")
 * @ORM\Entity(repositoryClass="App\Repository\SharedFormSmsMessageRepository")
 */
class SharedFormSmsMessage
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
     * @var FormsData
     *
     * @ORM\OneToOne(targetEntity="App\Entity\SharedForm")
     * @ORM\JoinColumn(name="shared_form_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $sharedForm;

    /**
     * @var string
     *
     * @ORM\Column(name="sid", type="string", length=40)
     */
    private $sid;


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
     * Set sharedForm.
     *
     * @param SharedForm $sharedForm
     *
     * @return SharedFormSmsMessage
     */
    public function setSharedForm(SharedForm $sharedForm)
    {
        $this->sharedForm = $sharedForm;

        return $this;
    }

    /**
     * Get sharedForm.
     *
     * @return SharedForm
     */
    public function getSharedForm()
    {
        return $this->sharedForm;
    }

    /**
     * Set sid.
     *
     * @param string $sid
     *
     * @return SharedFormSmsMessage
     */
    public function setSid($sid)
    {
        $this->sid = $sid;

        return $this;
    }

    /**
     * Get sid.
     *
     * @return string
     */
    public function getSid()
    {
        return $this->sid;
    }
}
