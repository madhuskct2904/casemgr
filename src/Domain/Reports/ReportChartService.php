<?php

namespace App\Domain\Reports;

use App\Entity\Accounts;
use App\Entity\ReportChart;
use App\Entity\ReportSummary;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;

class ReportChartService
{
    protected $em;
    protected $reportSummaryGenerator;
    protected $user;

    public function __construct(EntityManagerInterface $entityManager, ReportSummaryGenerator $reportSummaryGenerator)
    {
        $this->em = $entityManager;
        $this->reportSummaryGenerator = $reportSummaryGenerator;
    }

    public function setUser(Users $user): void
    {
        $this->user = $user;
    }

    public function getChartsIndex(int $reportId, Accounts $accounts): array
    {
        $findBy = [
            'id'      => $reportId,
            'account' => $accounts
        ];

        $report = $this->em->getRepository('App:Reports')->findOneBy($findBy);

        if (!$report) {
            throw new ReportChartServiceException('Invalid report');
        }

        $summaries = $report->getSummaries();

        $charts = [];

        foreach ($summaries as $summarySettings) {

            if (!$chart = $summarySettings->getChart()) {
                continue;
            }

            $summaryDTO = new ReportSummarySettingsDTO();
            $summaryDTO->setName($summarySettings->getName());
            $summaryDTO->setColumns($summarySettings->getColumns());
            $summaryDTO->setConditions($summarySettings->getConditions());
            $summaryDTO->setBaselineField($summarySettings->getBaselineField());
            $summaryDTO->setReportId($summarySettings->getReport()->getId());
            $summaryDTO->setUserId($this->user->getId());

            $summaryData = $this->reportSummaryGenerator->generateSummary($summaryDTO);

            // remove last row with sums
            array_pop($summaryData['rows']);

            $sets = $this->prepareDataSets($chart, $summaryData);

            $chartType = $chart->getType();

            $chartData = [
                'datasets' => $sets,
                'labels'   => array_column($summaryData['rows'], $chart->getLabels())
            ];

            $aspectRatio = $this->calculateAspectRatio($summaryData['rows']);

            $options = [
                'title'       => $chart->getTitle(),
                'aspectRatio' => $aspectRatio,
                'legend'      => [
                    'position' => 'bottom'
                ]
            ];

            $scales = [];

            if ($chartType !== 'pie' && ($xAxisLabel = $chart->getXAxisLabel())) {
                $scales['xAxes'][] = [
                    'scaleLabel' => [
                        'display'     => true,
                        'labelString' => $xAxisLabel
                    ]
                ];
            }

            if ($chartType !== 'pie' && ($yAxisLabel = $chart->getYAxisLabel())) {
                $scales['yAxes'][] = [
                    'scaleLabel' => [
                        'display'     => true,
                        'labelString' => $yAxisLabel
                    ]
                ];
            }

            if (count($scales)) {
                $options['scales'] = $scales;
            }

            $charts[] = [
                'type'       => $chartType,
                'summary_id' => $summarySettings->getId(),
                'chart_data' => $chartData,
                'options'    => $options
            ];

        }

        return $charts;
    }

    public function updateChart(ReportSummary $summary, bool $hasChart, array $chartData = []): void
    {
        $chart = $summary->getChart();

        if (!$hasChart) {
            if ($chart) {
                $this->em->remove($chart);
                $this->em->flush();
            }
            return;
        }

        if (!$chart) {
            $chart = new ReportChart();
        }

        $chart->setReportSummary($summary);
        $chart->setTitle($chartData['title'] ?? null);
        $chart->setType($chartData['type']);
        $chart->setXAxisLabel($chartData['label_x'] ?? null);
        $chart->setYAxisLabel($chartData['label_y'] ?? null);
        $chart->setLabels($chartData['labels'] ?? '');
        $chart->setDataSeries($chartData['data_series']);
        $this->em->persist($chart);
        $this->em->flush();
    }

    private function prepareDataSets(ReportChart $chart, array $summaryData): array
    {
        $dataSets = $chart->getDataSeries();

        $sets = [];

        foreach ($dataSets as $dataSet) {
            $multiplier = 1;

            if (strpos($dataSet, '_currency') !== false) {
                $multiplier = 0.01;
            }

            $sets[] = [
                'label' => $summaryData['columns_labels'][$dataSet],
                'data'  => array_map(function ($item) use ($multiplier) {
                    $val = filter_var($item, FILTER_SANITIZE_NUMBER_FLOAT);
                    if (is_numeric($val) && $multiplier) {
                        $val *= $multiplier;
                    }
                    return $val;
                }, array_column($summaryData['rows'], $dataSet)),
                'field' => $dataSet
            ];
        }
        return $sets;
    }

    private function calculateAspectRatio($rows)
    {
        $bars = count($rows);
        $factor = 200;
        $aspectRatio = $bars / ($bars * ($bars / $factor));

        if ($aspectRatio < 0.5) {
            $aspectRatio = 0.5;
        }

        if ($aspectRatio > 2) {
            $aspectRatio = 2;
        }

        return $aspectRatio;
    }

}
