<?php

namespace App\Handler\Modules;

use App\Entity\FormsData;

interface FormValuesHandlerInterface
{
    function setFormData(FormsData $formsData);
}
