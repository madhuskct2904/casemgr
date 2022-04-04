<?php

namespace App\EventListener;

use App\Entity\Accounts;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;

/**
 * Class AccountsSubscriber
 * @package App\EventListener
 */
class AccountsSubscriber implements EventSubscriber
{
    /**
     * @inheritdoc
     */
    public function getSubscribedEvents()
    {
        return [
            'onFlush'
        ];
    }

    /**
     * removes UsersSessions with disabled account
     *
     * @param OnFlushEventArgs $args
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $em     = $args->getEntityManager();
        $uow    = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Accounts) {
                $metadata           = $em->getClassMetadata('App\Entity\UsersSessions');
                $entityChangeSet    = $uow->getEntityChangeSet($entity);

                if (isset($entityChangeSet['status'])) {
                    $oldValue = $entityChangeSet['status'][0];
                    $newValue = $entityChangeSet['status'][1];

                    if (($oldValue !== $newValue) && ($newValue === 'Disabled')) {
                        $sessions = $em->getRepository('App:UsersSessions')->findBy([
                            'account' => $entity->getData()->getAccountUrl()
                        ]);

                        foreach ($sessions as $session) {
                            $em->remove($session);
                            $uow->computeChangeSet($metadata, $session);
                        }
                    }
                }
            }
        }
    }
}
