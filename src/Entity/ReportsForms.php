<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class ReportsForms
 * @package App\Entity
 *
 * @ORM\Table(name="reports_forms")
 * @ORM\Entity(repositoryClass="App\Repository\ReportsFormsRepository")
 */
class ReportsForms extends Entity
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Forms")
     * @ORM\JoinColumn(name="form_id", referencedColumnName="id", nullable=false)
     */
    protected $form;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Reports")
     * @ORM\JoinColumn(name="report_id", referencedColumnName="id", nullable=false)
     */
    protected $report;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="invalidated_at",type="datetime")
     */
    protected $invalidatedAt;

    /**
     * ReportsForms constructor.
     */
    public function __construct()
    {
        $this->invalidatedAt = null;
    }


    public function setForm(Forms $form)
    {
        $this->form = $form;
        return $this;
    }

    public function getForm()
    {
        return $this->form;
    }

    public function setReport(Reports $report)
    {
        $this->report = $report;
        return $this;
    }

    public function getReport()
    {
        return $this->report;
    }

    public function setInvalidatedAt(?\DateTime $invalidatedAt = null)
    {
        $this->invalidatedAt = $invalidatedAt;
        return $this;
    }

    public function getInvalidatedAt()
    {
        return $this->invalidatedAt;
    }
}
