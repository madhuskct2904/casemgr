<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ReportChart
 *
 * @ORM\Table(name="reports_charts")
 * @ORM\Entity(repositoryClass="App\Repository\ReportChartRepository")
 */
class ReportChart
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
     * @var int
     *
     * @ORM\OneToOne(targetEntity="App\Entity\ReportSummary")
     * @ORM\JoinColumn(name="summary_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private $reportSummary;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=32)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="labels", type="string", length=128)
     */
    private $labels;

    /**
     * @var string|null
     *
     * @ORM\Column(name="yAxisLabel", type="string", length=255, nullable=true)
     */
    private $yAxisLabel;

    /**
     * @var string|null
     *
     * @ORM\Column(name="xAxisLabel", type="string", length=255, nullable=true)
     */
    private $xAxisLabel;

    /**
     * @var string
     *
     * @ORM\Column(name="dataSeries", type="text")
     */
    private $dataSeries;


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
     * Set title.
     *
     * @param string $title
     *
     * @return ReportChart
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set type.
     *
     * @param string $type
     *
     * @return ReportChart
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set labels.
     *
     * @param string $labels
     *
     * @return ReportChart
     */
    public function setLabels($labels)
    {
        $this->labels = $labels;

        return $this;
    }

    /**
     * Get labels.
     *
     * @return string
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * Set yAxisLabel.
     *
     * @param string $yAxisLabel
     *
     * @return ReportChart
     */
    public function setYAxisLabel($yAxisLabel)
    {
        $this->yAxisLabel = $yAxisLabel;

        return $this;
    }

    /**
     * Get yAxisLabel.
     *
     * @return string
     */
    public function getYAxisLabel()
    {
        return $this->yAxisLabel;
    }

    /**
     * Set xAxisLabel.
     *
     * @param string $xAxisLabel
     *
     * @return ReportChart
     */
    public function setXAxisLabel($xAxisLabel)
    {
        $this->xAxisLabel = $xAxisLabel;

        return $this;
    }

    /**
     * Get xAxisLabel.
     *
     * @return string
     */
    public function getXAxisLabel()
    {
        return $this->xAxisLabel;
    }

    /**
     * Set dataSeries.
     *
     * @param array $dataSeries
     *
     * @return ReportChart
     */
    public function setDataSeries(array $dataSeries)
    {
        $this->dataSeries = json_encode($dataSeries);

        return $this;
    }

    /**
     * Get dataSeries.
     *
     * @return array
     */
    public function getDataSeries()
    {
        return json_decode($this->dataSeries, true);
    }

    public function setReportSummary(ReportSummary $reportSummary)
    {
        $this->reportSummary = $reportSummary;
        return $this;
    }

    public function getReportSummary()
    {
        return $this->reportSummary;
    }

}
