<?php

namespace App\EventListener;

use App\Entity\ActivityFeed;
use App\Entity\MassMessages;
use App\Event\MassMessagesCreatedEvent;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * Class MassMessagesListener
 * @package App\Event
 */
class MassMessagesListener
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * event data['massMessage'] = Entity\MassMessages
     *
     * @param MassMessagesCreatedEvent $event
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function onMassMessagesCreated(MassMessagesCreatedEvent $event)
    {
        $data = $event->getData();
        $activityFeed = new ActivityFeed();


        if ($data['massMessage'] instanceof MassMessages) {
            $this->em->refresh($data['massMessage']);

            $accountData = $this->em->getRepository('App:AccountsData')->findOneBy([
                'accountUrl' => $data['massMessage']->getUser()->getDefaultAccount()
            ]);

            $account = $this->em->getRepository('App:Accounts')->find($accountData->getId());

            $criteria = new Criteria();
            $criteria->where(Criteria::expr()->neq('status', 'system_administrator'));
            $criteria->andWhere(Criteria::expr()->eq('massMessage', $data['massMessage']));

            $count = $this->em->getRepository('App:Messages')->matching($criteria)->count();

            $details = [
                'massMessage' => $data['massMessage']->getId(),
                'countAll'    => $count
            ];

            $activityFeed
                ->setParticipant(null)
                ->setTemplate('mass_messages')
                ->setTemplateId($data['massMessage']->getId())
                ->setTitle($data['massMessage']->getUser() ? $data['massMessage']->getUser()->getData()->getFullName() : null)
                ->setAccount($account)
                ->setDetails($details);

            $this->em->persist($activityFeed);
            $this->em->flush();
        }
    }
}
