<?php

namespace App\Domain\DataImport;

interface ImportHandlerInterface
{
    public function getTemplateCsvHeader(): array;

    public function getTemplateCsvSubHeader(): array;

    public function getFields(): array;

    public function setImportKeyField(array $importKeyField): void;

    public function setImportCsvHeaderRow(array $importCsvHeaderRow): void;

    public function setImportFieldsMap(array $importFieldsMap): void;

    public function getImportKeyField(): array;

    public function getImportCsvHeaderRow(): array;

    public function getImportFieldsMap(): array;

    public function preValidate(array $csvRows, array $map, bool $ignoreRequired): array;
}
