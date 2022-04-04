<?php

namespace App\Event;

use App\Entity\SharedForm;
use Symfony\Contracts\EventDispatcher\Event;

class SharedFormEvent extends Event
{
    const FORM_SUBMISSION = 'shared_forms.submission';
    const FORM_SENT = 'shared_forms.sent';
    const FORM_SENDING_FAILED = 'shared_forms.failed';

    protected $sharedForm;

    public function __construct(SharedForm $sharedForm)
    {
        $this->sharedForm = $sharedForm;
    }

    public function getSharedForm(): SharedForm
    {
        return $this->sharedForm;
    }
}
