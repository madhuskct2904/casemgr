<?php

namespace App\Domain\Reports;

interface ReportSummaryRepositoryInterface
{
    public function findById($summaryId): ReportSummarySettingsDTO;
}
