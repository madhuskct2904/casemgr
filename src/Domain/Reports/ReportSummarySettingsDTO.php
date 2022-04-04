<?php

namespace App\Domain\Reports;

use App\Entity\Reports;

class ReportSummarySettingsDTO
{
    protected $name;
    protected $columns;
    protected $conditions;
    protected $baselineField;
    protected $reportId;
    protected $userId;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function setColumns(array $columns): void
    {
        $this->columns = $columns;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function setConditions(array $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function getBaselineField(): ?string
    {
        return $this->baselineField;
    }

    public function setBaselineField(?string $baselineField): void
    {
        $this->baselineField = $baselineField;
    }

    public function getReportId(): int
    {
        return $this->reportId;
    }

    public function setReportId(int $reportId): void
    {
        $this->reportId = $reportId;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }
}
