<?php

namespace App\Service;

use App\Entity\Accounts;
use App\Entity\Users;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class AlertsHelper
 * @package App\Service
 *
 * Helper class for alerts dropdown in frontend
 */
class AlertsHelper
{
    protected $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function getAlerts(Accounts $accounts, Users $user, $accessLevel = 0): array
    {
        $data = [
            'alerts'                => [],
            'sms_alerts'            => [],
            'referral_alerts'       => [],
            'assigned_case_manager' => [],
            'shared_form_completed' => [],
            'shared_form_failed'    => [],
            'count'                 => 0
        ];

        $smsAlerts = $this->em->getRepository('App:Messages')->getAlerts($user, $accounts);
        $data['sms_alerts'] = $this->groupByParticipant($smsAlerts);
        $data['count'] += count($smsAlerts);

        if ($accessLevel >= Users::ACCESS_LEVELS['SUPERVISOR']) {
            $referralAlerts = $this->em->getRepository('App:SystemMessage')->getUnreadReferralAlerts($user, $accounts);
            $data['count'] += count($referralAlerts);
            $data['referral_alerts'] = $this->prepareReferralAlerts($referralAlerts);
        }

        if ($accessLevel >= Users::ACCESS_LEVELS['CASE_MANAGER']) {
            $caseMgrAlerts = $this->em->getRepository('App:SystemMessage')->getCaseManagerAlerts($user, $accounts);

            foreach ($caseMgrAlerts as $alertType => $alerts) {
                $data[$alertType] = $this->groupByParticipant($alerts);
                $data['count'] += count($alerts);
            }

        }

        return $data;
    }

    /**
     * @param $alerts
     * @return array
     *
     * Return only last referral alert and count of all referral alerts
     */
    private function prepareReferralAlerts($alerts): array
    {
        if (!count($alerts)) {
            return ['count' => 0];
        }

        $lastAlert = $alerts[0];
        $lastAlert['count'] = count($alerts);
        return $lastAlert;
    }

    private function groupByParticipant($alerts): array
    {
        $groupedAlerts = [];

        foreach ($alerts as $alert) {
            if (isset($groupedAlerts[$alert['participantId']])) {
                $groupedAlerts[$alert['participantId']]['count']++;
                $groupedAlerts[$alert['participantId']]['createdAt'] = $alert['createdAt'];
                continue;
            }

            $groupedAlerts[$alert['participantId']] = $alert;
            $groupedAlerts[$alert['participantId']]['count'] = 1;
        }

        $sorted = [];

        foreach ($groupedAlerts as $data) {
            $createdAt = $data['createdAt'];

            if (true === is_string($createdAt)) {
                $createdAt = DateTime::createFromFormat('m/d/Y h:i A', $createdAt);
            }

            $timestamp = $createdAt->getTimestamp();

            $sorted[$timestamp] = $data;
        }

        krsort($sorted);

        return array_values($sorted);
    }
}
