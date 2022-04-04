<?php

namespace App\Domain\Reports;

use Doctrine\ORM\EntityManagerInterface;

final class DoctrineReportSummaryRepository implements ReportSummaryRepositoryInterface
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function findById($summaryId): ReportSummarySettingsDTO
    {
        $summary = $this->em->getRepository('App:ReportSummary')->find($summaryId);

        $dto = new ReportSummarySettingsDTO();
        $dto->setName($summary->getName());
        $dto->setColumns($summary->getColumns());
        $dto->setConditions($summary->getConditions());
        $dto->setBaselineField($summary->getBaselineField());
        $dto->setReportId($summary->getReport()->getId());

        return $dto;
    }

}
