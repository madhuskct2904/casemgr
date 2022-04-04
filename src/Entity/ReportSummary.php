<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ReportSummary
 *
 * @ORM\Table(name="report_summary")
 * @ORM\Entity(repositoryClass="App\Repository\ReportSummaryRepository")
 */
class ReportSummary
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
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var int
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Reports")
     * @ORM\JoinColumn(referencedColumnName="id", nullable=true, onDelete="CASCADE")
     */
    private $report;

    /**
     * @var string
     *
     * @ORM\Column(name="conditions", type="text")
     */
    private $conditions;

    /**
     * @var string
     *
     * @ORM\Column(name="baselineField", type="string", length=64)
     */
    private $baselineField;

    /**
     * @var string
     *
     * @ORM\Column(name="columns", type="text")
     */
    private $columns;


    /**
     * @var \Doctrine\Common\Collections\Collection|null
     *
     * @ORM\OneToOne(targetEntity="App\Entity\ReportChart", mappedBy="reportSummary", cascade={"remove"})
     */
    protected $chart;


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
     * @return ReportSummary
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
     * Set report.
     *
     * @param int $report
     *
     * @return ReportSummary
     */
    public function setReport($report)
    {
        $this->report = $report;

        return $this;
    }

    /**
     * Get report.
     *
     * @return int
     */
    public function getReport()
    {
        return $this->report;
    }

    /**
     * Set baselineField.
     *
     * @param string|null $baselineField
     *
     * @return ReportSummary
     */
    public function setBaselineField(?string $baselineField)
    {
        $this->baselineField = $baselineField;

        return $this;
    }

    /**
     * Get baselineField.
     *
     * @return string
     */
    public function getBaselineField()
    {
        return $this->baselineField;
    }

    /**
     * Set conditions.
     *
     * @param array $conditions
     *
     * @return ReportSummary
     */
    public function setConditions($conditions)
    {
        $this->conditions = json_encode($conditions ?: []);

        return $this;
    }

    /**
     * Get conditions.
     *
     * @return array
     */
    public function getConditions()
    {
        if (!$this->conditions) {
            return [];
        }

        return json_decode($this->conditions, true);
    }

    /**
     * Set columns.
     *
     * @param array $columns
     *
     * @return ReportSummary
     */
    public function setColumns($columns)
    {
        $this->columns = json_encode($columns ?: []);

        return $this;
    }

    /**
     * Get columns.
     *
     * @return array
     */
    public function getColumns()
    {
        if (!$this->columns) {
            return [];
        }

        return json_decode($this->columns, true);
    }

    /**
     * @return ReportChart|null
     */
    public function getChart(): ?ReportChart
    {
        return $this->chart;
    }

    /**
     * @param ReportChart|null $chart
     */
    public function setChart(?ReportChart $chart): void
    {
        $this->chart = $chart;
    }



}
