<?php


namespace App\Domain\DataImport;


use App\Enum\BasicEnum;

class ImportContext extends BasicEnum
{
    const FORM = 'form';
    const COMMUNICATION_NOTE = 'note';
}
