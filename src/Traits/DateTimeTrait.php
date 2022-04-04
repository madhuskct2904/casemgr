<?php

namespace App\Traits;

use App\Entity\Users;

trait DateTimeTrait
{
    public function convertDateTime(Users $user, \DateTime $datetime = null)
    {
        $timezone = $user->getData()->getTimeZone();
        $datetime = $datetime ? $datetime : new \DateTime();
        $timezones = $this->getTimeZones();
        $userFormat = $timezones[$timezone]['phpDateFormat'];
        $format = $userFormat . ' h:i A';

        try {
            $datetime->setTimezone(new \DateTimeZone($timezone));
        } catch (\Exception $e) {
            // ...
        }

        return $datetime->format($format);
    }

    abstract public function getTimeZones();
}
