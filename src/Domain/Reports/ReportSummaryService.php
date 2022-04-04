<?php

namespace App\Domain\Reports;

use App\Entity\Accounts;
use App\Entity\ReportSummary;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;

class ReportSummaryService
{
    protected $em;
    protected $reportSummaryGenerator;
    protected $reportChartDataMapper;
    protected $user;

    public function __construct(EntityManagerInterface $entityManager, ReportSummaryGenerator $reportSummaryGenerator, ReportChartDataMapper $reportChartDataMapper)
    {
        $this->em = $entityManager;
        $this->reportSummaryGenerator = $reportSummaryGenerator;
        $this->reportChartDataMapper = $reportChartDataMapper;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(Users $user): void
    {
        $this->user = $user;
    }

    public function create(ReportSummarySettingsDTO $reportSummarySettingsDTO): ReportSummary
    {
        $report = $this->em->getRepository('App:Reports')->find($reportSummarySettingsDTO->getReportId());
        $reportSummary = new ReportSummary();
        $reportSummary->setName($reportSummarySettingsDTO->getName());
        $reportSummary->setColumns($reportSummarySettingsDTO->getColumns());
        $reportSummary->setConditions($reportSummarySettingsDTO->getConditions());
        $reportSummary->setBaselineField($reportSummarySettingsDTO->getBaselineField());
        $reportSummary->setReport($report);
        $this->em->persist($reportSummary);
        $this->em->flush();

        return $reportSummary;
    }

    public function update(int $reportSummaryId, ReportSummarySettingsDTO $reportSummarySettingsDTO): ReportSummary
    {
        $reportSummary = $this->em->getRepository('App:ReportSummary')->find($reportSummaryId);

        if (!$reportSummary) {
            throw new \Exception('Report summary not found!');
        }

        $reportSummary->getId();
        $reportSummary->setName($reportSummarySettingsDTO->getName());
        $reportSummary->setColumns($reportSummarySettingsDTO->getColumns());
        $reportSummary->setConditions($reportSummarySettingsDTO->getConditions());
        $reportSummary->setBaselineField($reportSummarySettingsDTO->getBaselineField());
        $this->em->flush();

        return $reportSummary;
    }

    public function getReportForSummary(int $reportId, Accounts $account, int $accessLevel): array
    {
        $findBy = [
            'id'      => $reportId,
            'account' => $account
        ];

        if ($accessLevel < Users::ACCESS_LEVELS['SUPERVISOR']) {
            $findBy['status'] = 1;
        }

        $report = $this->em->getRepository('App:Reports')->findOneBy($findBy);

        if ($report === null) {
            throw new ReportSummaryServiceException('Invalid report ID');
        }

        $data = json_decode($report->getData(), true);

        $parsedData = [];

        foreach ($data as $formIdx => $formData) {
            $parsedData[$formIdx] = [
                'form_id'   => $formData['form_id'],
                'form_name' => $formData['form_name']
            ];

            foreach ($formData['fields'] as $fieldIdx => $fieldData) {
                if (isset($fieldData['columns']) && is_array($fieldData['columns'])) {
                    foreach ($fieldData['columns'] as $column) {
                        $suffix = '';

                        if (count($fieldData['columns']) > 1) {
                            $suffix = ' - ' . $column;
                        }

                        $parsedData[$formIdx]['fields'][] = [
                            'field'   => $fieldData['field'] . '-' . $column,
                            'form_id' => $fieldData['id'],
                            'label'   => $fieldData['label'] . $suffix
                        ];
                    }
                    continue;
                }

                $parsedData[$formIdx]['fields'][] = [
                    'field'   => $fieldData['field'],
                    'form_id' => $fieldData['id'],
                    'label'   => $fieldData['label'],
                ];

            }
        }

        $accounts = [];

        if ($report->getAccounts()) {
            $accounts = json_decode($report->getAccounts());
        }

        return [
            'id'           => $report->getId(),
            'name'         => $report->getName(),
            'description'  => $report->getDescription(),
            'user'         => $report->getUser()->getData()->getFullName(),
            'user_id'      => $report->getUser()->getId(),
            'created_date' => $report->getCreatedDate(),
            'status'       => $report->getStatus(),
            'data'         => $parsedData,
            'type'         => $report->getType(),
            'mode'         => $report->getMode(),
            'accounts'     => $accounts
        ];
    }

    public function getIndex(int $reportId): array
    {
        $summaryIndex = $this->em->getRepository('App:ReportSummary')->findBy(['report' => $reportId]);
        $index = [];

        foreach ($summaryIndex as $summary) {
            $summaryDTO = new ReportSummarySettingsDTO();
            $summaryDTO->setName($summary->getName());
            $summaryDTO->setColumns($summary->getColumns());
            $summaryDTO->setConditions($summary->getConditions());
            $summaryDTO->setBaselineField($summary->getBaselineField());
            $summaryDTO->setReportId($summary->getReport()->getId());
            $summaryDTO->setUserId($this->user->getId());

            $index[] = [
                    'id'         => $summary->getId(),
                    'chart_data' => $summary->getChart() ? $this->reportChartDataMapper->chartDataToArray($summary->getChart()) : null
                ] + $this->reportSummaryGenerator->generateSummary($summaryDTO);
        }

        return $index;
    }

}
