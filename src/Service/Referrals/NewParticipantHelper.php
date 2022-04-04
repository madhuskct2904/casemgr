<?php

namespace App\Service\Referrals;

use App\Service\AccountFormsService;
use Doctrine\ORM\EntityManagerInterface;

class NewParticipantHelper
{
    protected EntityManagerInterface $em;
    protected AccountFormsService $accountFormsService;


    public function __construct(EntityManagerInterface $em, AccountFormsService $accountFormsService)
    {
        $this->em = $em;
        $this->accountFormsService = $accountFormsService;
    }

    public function getDataForNewParticipant($referralId): array
    {
        $referral = $this->em->getRepository('App:Referral')->find($referralId);
        $formData = $referral->getFormData();

        $values = [];

        if (!$formData) {
            return $values;
        }

        $form = $formData->getForm();
        $referralToProfileFormMap = json_decode($form->getColumnsMap(), true);

        $fieldNamesMap = [];

        /** $item['value'] <- field name in referral form
         * $item['sourceValue'] <- field name in profile form
         */

        foreach ($referralToProfileFormMap as $item) {
            $fieldNamesMap[$item['value']] = $item['sourceValue'];
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $values;
        }

        $formValues = $formData->getValues();
        $referralValuesMap = [];
        $checkboxesGroups = [];

        foreach ($formValues as $referralFormValue) {
            $referralFormValueName = $referralFormValue->getName();

            if (strpos($referralFormValueName, 'checkbox-group') === 0) {
                $referralFieldName = substr($referralFormValueName, 0, strrpos($referralFormValueName, '-'));
                $referralFieldNo = substr($referralFormValueName, strrpos($referralFormValueName, '-') + 1);
                $profileFormFieldName = $fieldNamesMap[$referralFieldName];
                $checkboxesGroups[$referralFieldName][$profileFormFieldName . '-' . $referralFieldNo] = $referralFormValue->getValue();
                continue;
            }

            $referralValuesMap[$referralFormValueName] = $referralFormValue->getValue();
        }

        $keyValueMap = [];

        foreach ($referralToProfileFormMap as $srcItem) {
            $referralFormValue = $srcItem['value'];
            $sourceValue = $srcItem['sourceValue'];

            if (count($checkboxesGroups) && array_key_exists($referralFormValue, $checkboxesGroups) && strpos($sourceValue, 'checkbox-group') === 0) {
                foreach ($checkboxesGroups[$referralFormValue] as $valName => $valVal) {
                    $keyValueMap[$valName] = $valVal;
                }
                continue;
            }

            $keyValueMap[$sourceValue] = $referralValuesMap[$referralFormValue] ?? null;
        }

        return $keyValueMap;
    }

    public function getUserDataForNewParticipant($referralId): array
    {
        $profileToReferralFieldsMap = $this->getDataForNewParticipant($referralId);

        $referral = $this->em->getRepository('App:Referral')->find($referralId);
        $account = $referral->getAccount();

        $this->accountFormsService->setAccount($account);
        $destinationAccountProfileForm = $this->accountFormsService->getProfileForm();
        $profileFormColumnsMap = json_decode($destinationAccountProfileForm->getColumnsMap(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        $namedValuesMap = [];

        foreach ($profileFormColumnsMap as $mapping) {
            if (isset($profileToReferralFieldsMap[$mapping['value']])) {
                $namedValuesMap[$mapping['name']] = $profileToReferralFieldsMap[$mapping['value']];
            }
        }

        return $namedValuesMap;
    }
}
