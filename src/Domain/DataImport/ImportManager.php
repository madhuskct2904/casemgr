<?php

namespace App\Domain\DataImport;

use App\Entity\Imports;
use App\Utils\Helper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class ImportManager
{
    private $em;
    private $formId;
    private $importFileService;
    private $importHandler;

    public function __construct(
        EntityManagerInterface $em,
        ImportFileService $importFileService
    ) {
        $this->em = $em;
        $this->importFileService = $importFileService;
    }

    public function setup(int $formId)
    {
        $this->formId = $formId;

        if ($formId === 0) {
            $this->importHandler = new ImportNoteHandler($this->em);
        }

        if ($formId > 0) {

            $form = $this->em->getRepository('App:Forms')->find($formId);

            if (!$form) {
                throw new ImportManagerException('Invalid form!');
            }

            $this->importHandler = new ImportFormHandler($this->em);
            $this->importHandler->setForm($form);
        }
    }

    public function getHandler(): ?ImportHandlerInterface
    {
        return $this->importHandler;
    }

    public function getTemplate()
    {
        $data = [];

        $data[0] = $this->getHandler()->getTemplateCsvHeader();
        $data[0][] = 'Completed Date';

        $subHeader = $this->getHandler()->getTemplateCsvSubHeader();

        if (false === empty($subHeader)) {
            $subHeader[] = 'Completed Date';
            $data[1]     = $subHeader;
        }

        return Helper::csvConvert($data);
    }

    public function uploadFile($file)
    {
        $filename = sprintf('%s.%s', time() . $this->formId . mt_rand(), 'csv');

        try {
            $this->importFileService->upload($file, $filename);
        } catch (ImportFileServiceException $e) {
            throw new ImportManagerException('Upload error.');
        }

        $formFields = $this->getHandler()->getFields();

        return [
            'fields'           => [
                'file' => $this->importFileService->getCsvFromBucket($filename, 0, 1, $this)[0],
                'form' => $formFields ?? [],
            ],
            'filename'         => $filename,
            'formId'           => $this->formId
        ];
    }

    public function preValidate(string $filename, array $map, array $keyField, bool $ignoreRequired)
    {
        $csvRows = $this->importFileService->getCsvFromBucket($filename);
        $exceptions = $this->getHandler()->preValidate($csvRows, $map, $ignoreRequired);

        $totalCount = count($csvRows) - 1;
        $exceptionsCount = count($exceptions);

        $success = true;

        $errors = [];

        if ($totalCount === 0) {
            $errors[] = 'Data not found in file';
            $success = false;
        }

        if ($totalCount - $exceptionsCount === 0) {
            $errors[] = 'There are no valid rows in file';
        }

        $columns[] = [
            'field' => 'rowIdx',
            'label' => 'Row number'
        ];

        foreach ($this->getHandler()->getFields() as $field => $label) {
            $columns[] = [
                'field' => $field,
                'label' => $label
            ];
        }

        $unique = [];

        foreach ($map as $fieldName) {
            if ($fieldName != '' && in_array($fieldName, $unique)) {
                $errors[] = 'Non unique map fields';
                $success = false;
                break;
            }

            $unique[] = $fieldName;
        }

        if (!count($unique)) {
            $errors[] = 'Empty fields.';
            $success = false;
        }

        return [
            'map'             => $map,
            'keyField'        => $keyField,
            'count'           => $totalCount,
            'successCount'    => $totalCount - $exceptionsCount,
            'exceptionsCount' => $exceptionsCount,
            'columns'         => $columns,
            'exceptions'      => $exceptions,
            'exceptionsRows'  => array_column($exceptions, 'rowIdx'),
            'success'         => $success,
            'errors'          => $errors
        ];
    }




}
