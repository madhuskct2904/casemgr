<?php


namespace App\Domain\DataImport;

use App\Entity\Accounts;

interface ImportWorkerStrategyInterface
{
    public function setAccount(Accounts $accounts);

    public function getImportHandler(): ImportHandlerInterface;

    public function importCsvRow(array $csvRow): array;
}
