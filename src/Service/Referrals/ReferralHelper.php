<?php

namespace App\Service\Referrals;

use App\Entity\Accounts;
use App\Entity\Referral;
use App\Enum\ParticipantType;
use App\Domain\FormValues\ReferralHelperException;
use App\Service\FormDataService;
use Doctrine\ORM\EntityManagerInterface;

class ReferralHelper
{
    protected $em;
    protected $formDataService;

    public function __construct(EntityManagerInterface $em, FormDataService $formDataService)
    {
        $this->em = $em;
        $this->formDataService = $formDataService;
    }

    public function getParticipantName(Referral $referral, $lastNameFirst = false, $separator = ' ')
    {
        $participantName = '';
        $formData = $referral->getFormData();
        $formDataService = $this->formDataService;
        $formDataService->setFormData($formData);

        $account = $formData->getAccount();

        if (!$account instanceof Accounts) {
            throw new ReferralHelperException('Invalid account');
        }

        if ($account->getParticipantType() == ParticipantType::INDIVIDUAL) {
            if ($lastNameFirst) {
                $participantName = $formDataService->getMappedValue('last_name') . $separator . $formDataService->getMappedValue('first_name');
            } else {
                $participantName = $formDataService->getMappedValue('first_name') . $separator . $formDataService->getMappedValue('last_name');
            }
        }

        if ($account->getParticipantType() == ParticipantType::MEMBER) {
            $participantName = $formDataService->getMappedValue('name');
        }

        return $participantName;
    }

}
