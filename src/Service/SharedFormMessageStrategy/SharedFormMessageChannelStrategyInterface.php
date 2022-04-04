<?php

namespace App\Service\SharedFormMessageStrategy;

use App\Entity\SharedForm;

interface SharedFormMessageChannelStrategyInterface
{

    public function getStrategyName(): string;
    public function send(SharedForm $sharedForm);
    public function getStatus(): SharedFormMessageStatus;

}
