<?php

namespace App\Handler\Modules;

use App\Entity\MemberData;
use App\Utils\Helper;

final class MembersProfileValuesHandler extends FormValuesHandler
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

    private function updateUsersData(MemberData $memberData, Handler\Params $data): void
    {
        $memberData->setName($data->get('name', ''));
        $memberData->setPhoneNumber(Helper::convertPhone($data->get('phone_number', '')));
        $memberData->setJobTitle($data->get('job_title', ''));
        $memberData->setOrganizationId($data->get('organization_id', ''));

        // Generate system ID
        if ($memberData->getSystemId() === null or empty($memberData->getSystemId())) {
            $memberData->setSystemId(strtolower(Helper::generateCode(9))); // Generate system ID
        }

        if (!empty($data->get('date_completed', ''))) {
            $memberData->setDateCompleted(
                new \DateTime(
                    $data->get('date_completed', '')
                )
            );
        }

        $em = $this->em;
        $em->persist($memberData);
        $em->flush();
    }
}
