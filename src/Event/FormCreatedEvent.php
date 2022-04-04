<?php

namespace App\Event;

use App\Entity\FormsData;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class FormCreatedEvent
 * @package App\Event
 */
class FormCreatedEvent extends Event
{
    protected $formData;

    public function __construct(FormsData $formData)
    {
        $this->formData = $formData;
    }

    public function getFormData()
    {
        return $this->formData;
    }
}
