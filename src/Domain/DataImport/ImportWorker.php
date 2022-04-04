<?php

namespace App\Domain\DataImport;

use Doctrine\ORM\EntityManagerInterface;
use Dtc\QueueBundle\Model\Worker;
use Symfony\Component\HttpKernel\KernelInterface;

class ImportWorker extends Worker
{
    protected $em;
    protected $kernel;
    protected $importFileService;

    public function __construct(EntityManagerInterface $em, KernelInterface $kernel, ImportFileService $importFileService)
    {
        $this->em = $em;
        $this->kernel = $kernel;
        $this->importFileService = $importFileService;
    }

    public function getName()
    {
        return 'import';
    }

    public function runImport(int $importId, int $offset = 0, $limit = 50)
    {
        $import = $this->em->getRepository('App:Imports')->find($importId);

        if (!$import) {
            throw new \Exception('CRITICAL: Invalid import');
        }

        // skip header row
        if ($offset === 0) {
            ++$offset;
            --$limit;
        }

        $csvLines = $this->importFileService->getCsvFromBucket($import->getFile(), $offset, $limit);

        $container = $this->kernel->getContainer();
        $workerStrategyClass = 'app.import.worker_handler.' . $import->getContext();

        if (!$container->has($workerStrategyClass)) {
            $import->setStatus('error_invalid_handler');
            return;
        }

        $workerStrategy = $container->get($workerStrategyClass);
        $workerStrategy->setAccount($import->getAccount());
        $workerStrategy->setUser($import->getUser());
        $handler = $workerStrategy->getImportHandler();

        if ($form = $import->getForm()) {
            $handler->setForm($form);
        }

        $failedRows = $import->getFailedRows();
        $successRows = $import->getSuccessRows();
        $ignoreRows = $import->getIgnoreRows();

        $handler->setImportKeyField($import->getKeyField());
        $handler->setImportCsvHeaderRow($import->getCsvHeader());
        $handler->setImportFieldsMap($import->getMap());

        $import->setStatus(ImportStatus::PROCESSING);
        $this->em->flush();

        foreach ($csvLines as $rowIdx => $csvLine) {

            $import->setLastProcessedRow($rowIdx);

            if (in_array($rowIdx, $ignoreRows) || in_array($rowIdx, $successRows) || in_array($rowIdx, array_keys($failedRows))) {
                continue;
            }

            try {
                $workerStrategy->importCsvRow($csvLine);
                $successRows[] = $rowIdx;
            } catch (ImportWorkerException $e) {
                $err = json_decode($e->getMessage(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $err = $e->getMessage();
                }

                $failedRows[$rowIdx] = [
                    'rowIdx'    => $rowIdx,
                    'values'    => $csvLine,
                    'error'     => $err,
                    'timestamp' => time()
                ];
            } catch (\Exception $e) {
                $err = json_decode($e->getMessage(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $err = $e->getMessage();
                }

                $failedRows[$rowIdx] = [
                    'rowIdx'    => $rowIdx,
                    'values'    => $csvLine,
                    'error'     => $err,
                    'timestamp' => time()
                ];
                $import->setStatus(ImportStatus::ERROR);
            }

        }

        if ($import->getLastProcessedRow() === $import->getTotalRows()) {
            $import->setStatus(ImportStatus::FINISHED);
        }

        $import->setFailedRows($failedRows);
        $import->setSuccessRows($successRows);

        $this->em->flush();
    }
}
