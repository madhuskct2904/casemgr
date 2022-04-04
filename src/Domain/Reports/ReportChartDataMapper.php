<?php

namespace App\Domain\Reports;

use App\Entity\ReportChart;

class ReportChartDataMapper
{
    public function chartDataToArray(ReportChart $chart): array
    {
        return [
            'id'           => $chart->getId(),
            'title'        => $chart->getTitle(),
            'type'         => $chart->getType(),
            'labels'       => $chart->getLabels(),
            'y_axis_label' => $chart->getYAxisLabel(),
            'x_axis_label' => $chart->getXAxisLabel(),
            'data_series'  => $chart->getDataSeries()
        ];
    }

}
