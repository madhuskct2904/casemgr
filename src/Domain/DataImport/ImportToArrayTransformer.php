<?php

namespace App\Domain\DataImport;

use App\Entity\Imports;

class ImportToArrayTransformer
{
    public static function importToArray(Imports $import): array
    {
        return [
            'id'                      => $import->getId(),
            'file'                    => $import->getFile(),
            'original_file'           => $import->getOriginalFile(),
            'created'                 => [
                'date' => $import->getCreatedDate(),
                'by'   => $import->getUser() ? $import->getUser()->getData()->getFullName(false) : ''
            ],
            'form'                    => [
                'id'      => $import->getForm() ? $import->getForm()->getId() : null,
                'name'    => $import->getForm() ? $import->getForm()->getName() : 'Case Notes',
                'account' => $import->getForm() ? $import->getFormAccount()->getId() : null
            ],
            'account'                 => [
                'id' => $import->getAccount() ? $import->getAccount()->getId() : null,
            ],
            'ignored_rows'            => $import->getIgnoreRows(),
            'failed_rows'             => $import->getFailedRows(),
            'success_rows'            => $import->getSuccessRows(),
            'total_file_rows'         => $import->getTotalRows(),
            'total_import_rows_count' => (int)($import->getIgnoreRows() ? $import->getTotalRows() - count($import->getIgnoreRows()) : $import->getTotalRows()),
            'status'                  => $import->getStatus(),
            'success_rows_count'      => $import->getSuccessRows() ? count($import->getSuccessRows()) : 0,
            'ignored_rows_count'      => count($import->getIgnoreRows()),
            'failed_rows_count'       => $import->getFailedRows() ? count($import->getFailedRows()) : 0,
            'csv_header'              => $import->getCsvHeader()
        ];
    }
}
