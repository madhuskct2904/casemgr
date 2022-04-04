<?php

namespace App\Handler\Modules;

use App\Entity\UsersData;
use App\Utils\Helper;
use DateTime;

final class ParticipantsProfileValuesHandler extends FormValuesHandler
{
    public function handle()
    {
        $participantUser = $this->em->getRepository('App:Users')->find($this->getFormData()->getElementId());
        $participantUserData = $participantUser->getData();

        $values = $this->getFormData()->getValues();
        $valuesArr = [];

        foreach ($values as $value) {
            $valuesArr[] = [
                'name'  => $value->getName(),
                'value' => $value->getValue()
            ];
        }

        $params = $this->columnsMapValues($valuesArr);
        $this->updateUsersData($participantUserData, $params);
    }

    private function updateUsersData(UsersData $participantUserData, Handler\Params $data): void
    {
        // Users data
        $participantUserData->setGender($data->get('gender', ''));
        $participantUserData->setPhoneNumber(Helper::convertPhone($data->get('phone_number', '')));
        $participantUserData->setFirstName($data->get('first_name', ''));
        $participantUserData->setLastName($data->get('last_name', ''));
        $participantUserData->setJobTitle($data->get('job_title', ''));
        $participantUserData->setOrganizationId($data->get('organization_id', ''));

        // Generate system ID
        if ($participantUserData->getSystemId() === null or empty($participantUserData->getSystemId())) {
            $participantUserData->setSystemId(strtolower(Helper::generateCode(9))); // Generate system ID
        }

        $birthDate  = $data->get('date_birth');
        $dateFormat = $this->getDateFormat();

        if (false === empty($birthDate)) {
            $participantUserData->setDateBirth(
                DateTime::createFromFormat($dateFormat, $birthDate)
            );
        }

        $completedDate = $data->get('date_completed');

        if (false === empty($completedDate)) {
            $participantUserData->setDateCompleted(
                DateTime::createFromFormat($dateFormat, $completedDate)
            );
        }

        $em = $this->em;

        $em->persist($participantUserData);
        $em->flush();
    }
}
