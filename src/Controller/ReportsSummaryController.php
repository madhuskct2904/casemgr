<?php

namespace App\Controller;

use App\Domain\Reports\ReportChartDataMapper;
use App\Domain\Reports\ReportChartService;
use App\Domain\Reports\ReportSummaryGenerator;
use App\Domain\Reports\ReportSummaryService;
use App\Domain\Reports\ReportSummarySettingsDTO;
use App\Exception\ExceptionMessage;
use Exception;
use function Sentry\captureException;

class ReportsSummaryController extends Controller
{
    public function previewAction(ReportSummaryGenerator $reportSummaryGenerator)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $reportSummaryDTO = $this->prepareReportSummaryDTOFromRequest();

        try {
            $view = $reportSummaryGenerator->generateSummary($reportSummaryDTO);
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success([
            'summary_table' => $view['rows'],
            'columns'       => $view['columns']
        ]);
    }

    public function getReportForSummaryAction(int $reportId, ReportSummaryService $reportSummaryService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $reportSummaryService->setUser($this->user());

        try {
            $reportData = $reportSummaryService->getReportForSummary($reportId, $this->account(), $this->access());
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success($reportData);
    }

    public function createAction(
        ReportSummaryService $reportSummaryService,
        ReportChartService $reportChartService
    )
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $reportSummaryDTO = $this->prepareReportSummaryDTOFromRequest();
        $reportSummaryService->setUser($this->user());
        $hasChart = $this->getRequest()->param('has_chart', false);
        $chartData = $this->getRequest()->param('chart_data');

        try {
            $summary = $reportSummaryService->create($reportSummaryDTO);
            $chart = $reportChartService->updateChart($summary, $hasChart, $chartData);
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success([
            'message'  => 'Summary created',
            'id'       => $summary->getId(),
            'chart_id' => $chart ? $chart->getId() : null
        ]);
    }

    public function indexAction(ReportSummaryService $reportSummaryService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $reportSummaryService->setUser($this->user());
        $reportId = $this->getRequest()->param('report_id');
        $index = $reportSummaryService->getIndex($reportId);

        return $this->getResponse()->success([
            'index' => $index
        ]);
    }

    public function editAction(ReportChartDataMapper $reportChartDataMapper)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $summaryId = $this->getRequest()->param('summary_id');
        $summary = $this->getDoctrine()->getRepository('App:ReportSummary')->find($summaryId);

        if (!$summary) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_REPORT_SUMMARY);
        }

        $hasChart = false;
        $chartData = [];

        if ($chart = $summary->getChart()) {
            $hasChart = true;
            $chartData = $reportChartDataMapper->chartDataToArray($chart);
        }

        return $this->getResponse()->success([
            'columns'        => $summary->getColumns(),
            'conditions'     => $summary->getConditions(),
            'baseline_field' => $summary->getBaselineField(),
            'name'           => $summary->getName(),
            'has_chart'      => $hasChart,
            'chart_data'     => $chartData
        ]);
    }

    public function updateAction(
        ReportSummaryService $reportSummaryService,
        ReportChartService $reportChartService
    )
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $summaryId = $this->getRequest()->param('summary_id');
        $summaryDTO = $this->prepareReportSummaryDTOFromRequest();

        $reportSummaryService->setUser($this->user());

        $summary = $this->getDoctrine()->getRepository('App:ReportSummary')->find($summaryId);

        if (!$summary) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_REPORT_SUMMARY);
        }

        $hasChart = $this->getRequest()->param('has_chart', false);
        $chartData = $this->getRequest()->param('chart_data');

        try {
            $reportSummaryService->update($summaryId, $summaryDTO);
            $reportChartService->updateChart($summary, $hasChart, $chartData);
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success(['message' => 'Summary updated.']);
    }

    public function deleteAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $summaryId = $this->getRequest()->param('summary_id');
        $em = $this->getDoctrine()->getManager();
        $reportSummary = $em->getRepository('App:ReportSummary')->find($summaryId);

        if (!$reportSummary) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_REPORT_SUMMARY);
        }

        $em->remove($reportSummary);
        $em->flush();

        return $this->getResponse()->success(['message' => 'Report summary removed!']);
    }

    public function checkIfReportHasSummaryAction($reportId)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $report = $this->getDoctrine()->getRepository('App:Reports')->find($reportId);

        $summaries = $report->getSummaries();

        if (count($summaries)) {
            return $this->getResponse()->success(['has_summary' => true]);
        }

        return $this->getResponse()->success(['has_summary' => false]);
    }

    protected function prepareReportSummaryDTOFromRequest(): ReportSummarySettingsDTO
    {
        $reportId = $this->getRequest()->param('report_id');
        $baselineField = $this->getRequest()->param('baseline_field', null);
        $conditions = $this->getRequest()->param('conditions', []);
        $columns = $this->getRequest()->param('columns', []);
        $name = $this->getRequest()->param('name');

        $reportSummaryDTO = new ReportSummarySettingsDTO();
        $reportSummaryDTO->setName($name);
        $reportSummaryDTO->setConditions($conditions);
        $reportSummaryDTO->setColumns($columns);
        $reportSummaryDTO->setBaselineField($baselineField);
        $reportSummaryDTO->setReportId($reportId);
        $reportSummaryDTO->setUserId($this->user()->getId());

        return $reportSummaryDTO;
    }

}
