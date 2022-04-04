<?php

namespace App\EventListener;

use App\Entity\ActivityFeed;
use App\Entity\Forms;
use App\Event\ParticipantRemovedEvent;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;

class ParticipantListener
{
    protected ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }
    public function onParticipantRemoved(ParticipantRemovedEvent $event)
    {
        $participant = $event->getParticipant();

        $em = $this->doctrine->getManager();

        $referrals = $em->getRepository('App:Referral')->findBy([
            'enrolledParticipant' => $participant
        ]);

        if (!$referrals) {
            return;
        }

        foreach ($referrals as $referral) {
            $formData = $referral->getFormData();
            $this->invalidateReports($formData->getForm());
            $em->remove($formData);

            $activityFeed = $em->getRepository('App:ActivityFeed')->findBy([
                'template'   => 'referral_not_enrolled',
                'templateId' => $referral->getId()
            ]);

            foreach ($activityFeed as $feedItem) {
                $em->remove($feedItem);
            }

            $em->remove($referral);
        }
    }

    private function invalidateReports(Forms $form)
    {
        $em = $this->doctrine->getManager();
        $em->getRepository('App:ReportsForms')->invalidateForm($form);
    }

}
