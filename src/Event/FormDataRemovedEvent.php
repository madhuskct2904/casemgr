<?php

namespace App\Event;

use App\Entity\Accounts;
use App\Entity\Assignments;
use App\Entity\Forms;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class FormEvent
 * @package App\Event
 */
class FormDataRemovedEvent extends Event
{
    const FORM_DATA_REMOVED = 'form_data.removed';

    protected $form;
    protected $account;
    protected $participantUserId;
    protected $assignment;

    public function __construct(Forms $form, Accounts $account, ?int $participantUserId = null, ?Assignments $assignment)
    {
        $this->form = $form;
        $this->account = $account;
        $this->participantUserId = $participantUserId;
        $this->assignment = $assignment;
    }

    public function getForm(): Forms
    {
        return $this->form;
    }

    public function getAccount(): Accounts
    {
        return $this->account;
    }

    public function getParticipantUserId(): ?int
    {
        return $this->participantUserId;
    }

    public function getAssignment()
    {
        return $this->assignment;
    }


}
