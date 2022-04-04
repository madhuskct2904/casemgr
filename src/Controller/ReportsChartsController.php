<?php

namespace App\Controller;

use App\Domain\Reports\ReportChartService;
use App\Entity\ReportChart;
use App\Exception\ExceptionMessage;
use Exception;
use function Sentry\captureException;

class ReportsChartsController extends Controller
{
    public function indexAction($reportId, ReportChartService $reportChartService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        try {
            $reportChartService->setUser($this->user());
            $charts = $reportChartService->getChartsIndex($reportId, $this->account());
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success([
            'charts' => $charts
        ]);
    }
}
