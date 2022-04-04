<?php

namespace App\Domain\DataImport;

use App\Enum\BasicEnum;

class ImportStatus extends BasicEnum
{
    const FINISHED = 'finished';
    const PENDING = 'pending';
    const PROCESSING = 'processing';
    const ERROR = 'error';
}
