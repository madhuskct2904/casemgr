<?php

namespace App\Domain\DataImport;

use Doctrine\ORM\EntityManagerInterface;

class ImportNoteHandler extends BaseImportHandler
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getFields(): array
    {
        return [
            'note'                  => 'Communication Notes',
            'type'                  => 'Communication Type',
            'import-completed-date' => 'Completed Date'
        ];
    }

    public function getTemplateCsvHeader(): array
    {
        $columns = array_values($this->getFields());
        $columns[] = 'System ID';

        return $columns;
    }

    function preValidate(array $csvRows, array $map, bool $ignoreRequired): array
    {
        return [];
    }
}
