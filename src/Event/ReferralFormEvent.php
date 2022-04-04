<?php

namespace App\Event;

use App\Entity\FormsData;
use Symfony\Contracts\EventDispatcher\Event;

class ReferralFormEvent extends Event
{
    const REFERRAL_FORM_FILLED = 'referral_form.filled';

    protected $formsData;

    public function __construct(FormsData $formsData)
    {
        $this->formsData = $formsData;
    }

    public function getFormData()
    {
        return $this->formsData;
    }
}
