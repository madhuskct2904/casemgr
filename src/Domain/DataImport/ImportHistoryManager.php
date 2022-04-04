<?php


namespace App\Domain\DataImport;

use App\Entity\Accounts;
use App\Entity\Imports;
use Aws\S3\Exception\S3Exception;
use Doctrine\ORM\EntityManagerInterface;

class ImportHistoryManager
{
    private $em;
    private $importFileService;
    private $account = null;

    public function __construct(EntityManagerInterface $em, ImportFileService $importFileService)
    {
        $this->em = $em;
        $this->importFileService = $importFileService;
    }

    public function getAccount()
    {
        return $this->account;
    }

    public function setAccount(Accounts $account): void
    {
        $this->account = $account;
    }

    public function deleteExpired()
    {
        $expired = $this->em->getRepository('App:Imports')->findExpired($this->getAccount());

        foreach ($expired as $import) {
            try {
                $this->importFileService->deleteFile($import->getFile());
            } catch (S3Exception $e) {
                throw new ImportManagerException('Something went wrong while trying to remove file from S3 bucket.');
            }
            $this->em->remove($import);
        }

        $this->em->flush();
    }

    public function getHistoryIndex(): array
    {
        $imports = $this->em->getRepository('App:Imports')->findHistory($this->getAccount());
        $data = [];

        foreach ($imports as $import) {
            $data[] = ImportToArrayTransformer::importToArray($import);
        }

        return $data;
    }

    public function show(Imports $import): array
    {
        $data = ImportToArrayTransformer::importToArray($import);

        $fileRows = $this->importFileService->getCsvFromBucket($import->getFile(), 0);

        $columns[] = [
            'label' => 'Line',
            'field' => 'line'
        ];

        $failedRows = [];

        foreach ($fileRows as $rowIdx => $rowData) {
            if (in_array($rowIdx, array_keys($import->getFailedRows())) || in_array($rowIdx, $import->getIgnoreRows())) {
                foreach ($rowData as $colIdx => $rowValue) {
                    $failedRows[$rowIdx]['line'] = $rowIdx;
                    $failedRows[$rowIdx]['col' . $colIdx] = $rowValue;
                }
            }
        }

        foreach ($import->getCsvHeader() as $idx => $csvHeader) {
            $columns[] = [
                'label' => $csvHeader,
                'field' => 'col' . $idx
            ];
        }


        return [
            'data'       => $data,
            'columns'    => $columns,
            'failedRows' => array_values($failedRows)
        ];
    }

    public function exportExceptions(Imports $import)
    {
        $fileRows = $this->importFileService->getCsvFromBucket($import->getFile(), 0);

        $rows[] = $import->getCsvHeader();

        foreach ($fileRows as $rowIdx => $rowData) {
            if (in_array($rowIdx, array_keys($import->getFailedRows())) || in_array($rowIdx, $import->getIgnoreRows())) {
                $rows[] = $rowData;
            }

        }

        return $rows;
    }
}
