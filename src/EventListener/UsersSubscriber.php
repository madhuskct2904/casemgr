<?php

namespace App\EventListener;

use App\Entity\Users;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;

/**
 * Class UsersSubscriber
 * @package App\EventListener
 */
class UsersSubscriber implements EventSubscriber
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
     * @param OnFlushEventArgs $args
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $em     = $args->getEntityManager();
        $uow    = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Users) {
                $entityChangeSet = $uow->getEntityChangeSet($entity);
                if (isset($entityChangeSet['password'])) {
                    $entity->setPasswordSetAt(new \DateTime());
                    $uow->recomputeSingleEntityChangeSet($em->getClassMetadata('App\Entity\Users'), $entity);
                }
            }
        }
    }
}
