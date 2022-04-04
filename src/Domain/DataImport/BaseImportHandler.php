<?php

namespace App\Domain\DataImport;

abstract class BaseImportHandler implements ImportHandlerInterface
{

    protected $importKeyField;
    protected $importHeaderCsvRow;
    protected $importFieldsMap;

    public function getTemplateCsvSubHeader(): array
    {
        return [];
    }

    public function setImportKeyField(array $keyField): void
    {
        $this->importKeyField = $keyField;
    }

    public function setImportCsvHeaderRow(array $csvRow): void
    {
        $this->importHeaderCsvRow = $csvRow;
    }

    public function setImportFieldsMap(array $importFieldsMap): void
    {
        $this->importFieldsMap = $importFieldsMap;
    }

    public function getImportKeyField(): array
    {
        return $this->importKeyField;
    }

    public function getImportCsvHeaderRow(): array
    {
        return $this->importHeaderCsvRow;
    }

    public function getImportFieldsMap(): array
    {
        return $this->importFieldsMap;
    }


}
