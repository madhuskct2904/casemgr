<?php


namespace App\Enum;

class ReportMode extends BasicEnum
{
    const SINGLE = 1;
    const MULTIPLE = 2;
    const SINGLE_MIRROR_PARENT = 3;
    const MULTIPLE_MIRROR_PARENT = 4;
    const MIRROR_CHILD = 5;
}
