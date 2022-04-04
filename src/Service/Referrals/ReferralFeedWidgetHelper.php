<?php

namespace App\Service\Referrals;

use App\Entity\Accounts;
use App\Enum\ParticipantType;
use App\Service\FormDataService;
use Doctrine\ORM\EntityManagerInterface;

class ReferralFeedWidgetHelper
{
    protected $em;
    protected $formDataService;

    public function __construct(EntityManagerInterface $em, FormDataService $formDataService)
    {
        $this->em = $em;
        $this->formDataService = $formDataService;
    }

    public function prepareReferralFeedWidgetData(Accounts $account)
    {
        $referralRepository = $this->em->getRepository('App:Referral');
        $referrals = $referralRepository->findBy(['account' => $account], ['createdAt' => 'DESC']);

        $referralsArr = [];

        foreach ($referrals as $referral) {
            $this->formDataService->setFormData($referral->getFormData());

            $name = '';

            if ($account->getParticipantType() == ParticipantType::INDIVIDUAL) {
                $name = $this->formDataService->getMappedValue('first_name') . ' ' . $this->formDataService->getMappedValue('last_name');
            }

            if ($account->getParticipantType() == ParticipantType::MEMBER) {
                $name = $this->formDataService->getMappedValue('name');
            }


            $referralsArr[$referral->getStatus()][] =
                [
                    'id'                      => $referral->getId(),
                    'data_id'                 => $referral->getFormData()->getId(),
                    'name'                    => $name,
                    'created_at'              => $referral->getCreatedAt(),
                    'enrolled_participant_id' => $referral->getEnrolledParticipant() ? $referral->getEnrolledParticipant()->getId() : null
                ];
        }

        return $referralsArr;
    }

}
