<?php

namespace App\EventListener;

use App\Entity\UsersActivityLog;
use App\Event\UserLastActionEvent;
use App\Event\UserLoginFailureEvent;
use App\Event\UserLoginSuccessEvent;
use App\Event\UserLogoutEvent;
use App\Event\UserSecurityViolationEvent;
use App\Event\UserSessionTimeoutEvent;
use App\Event\UserSwitchedAccountEvent;
use Doctrine\ORM\EntityManagerInterface;

class UsersListener
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function onUserLoginSuccess(UserLoginSuccessEvent $event)
    {
        $this->logUserActivityEvent(UserLoginSuccessEvent::NAME, $event);
    }

    public function onUserLoginFailure(UserLoginFailureEvent $event)
    {
        $this->logUserActivityEvent(UserLoginFailureEvent::NAME, $event);
    }

    public function onUserLogout(UserLogoutEvent $event)
    {
        $this->logUserActivityEvent(UserLogoutEvent::NAME, $event);
    }

    public function onUserSessionTimeout(UserSessionTimeoutEvent $event)
    {
        $user = $event->getUser();
        $log = $this->em->getRepository('App:UsersActivityLog')->findOneBy(['user' => $user], ['dateTime'=>'DESC']);

        if ($log && $log->getEventName() == UserSessionTimeoutEvent::NAME) {
            $log->setDateTime(new \DateTime());
            $this->em->flush();
            return;
        }

        $this->logUserActivityEvent(UserSessionTimeoutEvent::NAME, $event);
    }

    public function onUserSecurityViolation(UserSecurityViolationEvent $event)
    {
        $this->logUserActivityEvent(UserSecurityViolationEvent::NAME, $event);
    }

    public function onUserSwitchedAccount(UserSwitchedAccountEvent $event)
    {
        $this->logUserActivityEvent(UserSwitchedAccountEvent::NAME, $event);
    }

    public function onUserLastAction(UserLastActionEvent $event)
    {
        $user = $event->getUser();
        $log = $this->em->getRepository('App:UsersActivityLog')->findOneBy(['user' => $user], ['dateTime'=>'DESC']);

        if ($log && $log->getEventName() == UserLastActionEvent::NAME) {
            $log->setDateTime(new \DateTime());
            $this->em->flush();
            return;
        }

        $this->logUserActivityEvent(UserLastActionEvent::NAME, $event);
    }

    private function logUserActivityEvent(string $eventType, $event)
    {
        $userActivityLog = new UsersActivityLog();

        $userActivityLog->setEventName($eventType);
        $userActivityLog->setUser($event->getUser());
        $userActivityLog->setAccount($event->getAccounts());

        $details = array_merge($event->getDetails(), [
            'email'        => $event->getUser() ? $event->getUser()->getEmailCanonical() : '',
            'user_id'      => $event->getUser() ? $event->getUser()->getId() : '',
            'account_name' => $event->getAccounts() ? $event->getAccounts()->getOrganizationName() : '',
            'account_id'   => $event->getAccounts() ? $event->getAccounts()->getId() : '',
        ]);

        $userActivityLog->setDetails($details);
        $userActivityLog->setMessage($event->getMessage());
        $userActivityLog->setDateTime(new \DateTime());

        $this->em->persist($userActivityLog);
        $this->em->flush();
    }
}
