<?php

namespace App\Controller;

use App\Entity\Accounts;
use App\Entity\Credentials;
use App\Entity\Users;
use App\Event\UserLastActionEvent;
use App\Event\UserSessionTimeoutEvent;
use App\Event\UserSecurityViolationEvent;
use App\Exception\AuthException;
use App\Library\UrlParser;
use App\Service\Request;
use App\Service\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller as ParentController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use App\Traits\DateTimeTrait;

/**
 * Class Controller
 *
 * @package App\Controller
 */
class Controller extends ParentController
{
    use DateTimeTrait;

    /**
     * @return null|string
     */
    public function getToken(): ?string
    {
        if ($this->getRequest()->hasHeader('token')) {
            return $this->getRequest()->header('token');
        }

        return $this->getRequest()->getQuery('token');
    }

    /**
     * @return bool
     */
    public function isToken(): bool
    {
        if ($this->getRequest()->hasHeader('token')) {
            return true;
        }

        if ($this->getRequest()->hasQuery('token')){
            return true;
        }

        return false;
    }

    /**
     * @param bool $expired
     *
     * @return bool
     */
    public function checkToken(bool $expired = true): bool
    {
        $token = $this->getToken();
        $time = new \DateTime();

        if ($this->isToken() === false) {
            header("HTTP/1.1 401 Unauthorized");
            return false;
        }

        $session = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneByToken($token);

        if ($session !== null) {
            $eventDispatcher = $this->get('App\Service\EventDispatcherFactoryService')->getEventDispatcher();
            $expired_time = $session->getExpiredDate();

            if ($expired_time < $time) {
                $eventDispatcher->dispatch(
                    new UserSessionTimeoutEvent($session->getUser(), null, 'Session timeout'),
                    UserSessionTimeoutEvent::class
                );

                header("HTTP/1.1 401 Unauthorized");
                return false;
            }

            if ($expired === true) {
                // account
                if (!$this->account()) {
                    header("HTTP/1.1 401 Unauthorized");

                    return false;
                }

                $expired_time = new \DateTime();
                $expired_time->modify('+30 minutes');

                $session->setExpiredDate($expired_time);
                $session->setLastActionDate(new \DateTime());

                // Save
                $em = $this->getDoctrine()->getManager();

                $em->persist($session);
                $em->flush();
            }

            $eventDispatcher->dispatch(
                new UserLastActionEvent($session->getUser(), null, 'Last User Activity'),
                UserLastActionEvent::class
            );

            return true;
        }

        header("HTTP/1.1 401 Unauthorized");

        return false;
    }

    /**
     * @return Users|null
     */
    public function user(): ?Users
    {
        $token = $this->getToken();

        if ($token !== null) {
            $user = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneByToken($token);

            if ($user === null) {
                return null;
            }

            return $user->getUser();
        }

        return null;
    }

    /**
     * @param Users|null $user
     * @return int
     */
    public function access(Users $user = null): int
    {
        if (!$user = $user ? $user : $this->user()) {
            return 0;
        }

        $account = $this->account($user);

        if ($account && count($accounts = $user->getAccounts())) {
            $col = $accounts->filter(
                function ($entry) use ($account) {
                    return $entry->getId() === $account->getId();
                }
            );

            if ($credential = $user->getCredential($col->first() === false ? null : $col->first())) {
                return $credential->getAccess();
            }
        }

        return 0;
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->get('App\Service\Request');
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->get('App\Service\Response');
    }

    /**
     * @param Users|null $user
     * @return Accounts|null
     */
    public function account(Users $user = null): ?Accounts
    {
        if (!$user = $user ? $user : $this->user()) {
            return null;
        }

        $urlParser = $this->container->get('App\Service\UrlParser');

        $headers        = $this->getRequest()->headers();
        $origin         = $headers['origin'] ?? $headers['referer'];
        $accountUrl     = $urlParser->normalize($origin);

        // First, check if the user is a member of the account indicated by the current URL
        if ($accountUrl !== null) {
            // $accountUrl is not null, meaning the current URL has a subdomain
            $matchingAccounts = $user
                ->getAccounts()
                ->filter(
                    function ($entry) use ($accountUrl) {
                        return $entry->getData()->getAccountUrl() === $accountUrl;
                    }
                );

            if (!count($matchingAccounts)) {
                // None of the user's accounts match the current subdomain
                return null;
            }
        }

        // Attempt to access the user's `default_account`
        $defaultAccountUrl = $user->getDefaultAccount();

        if ($defaultAccountUrl) {
            $convertedDefaultAccountUrl = $urlParser->normalize($defaultAccountUrl);

            $account = $this
                ->getDoctrine()
                ->getRepository('App:Accounts')
                ->findForAuth($convertedDefaultAccountUrl, $user);

            if ($convertedDefaultAccountUrl && $account) {
                // If the user has a default account, and they have credentials for that account, return it
                return $account;
            }
        }


        // If access to default_account was unsuccessful, try the account that is in the url
        if ($accountUrl) {
            return $this->getDoctrine()->getRepository('App:Accounts')->findForAuth($accountUrl, $user);
        }

        /**
         *  Reaching this point means that:
         *   1. There is no account subdomain in the URL, and
         *   2. The user either doesn't have a default account, or they don't have access to their
         *      default account
         *
         *  In this case, return the first enabled account to which they have access
         */
        $credentials = ($user->getCredentials()->filter(
            function ($entry) {
                return $entry->isEnabled();
            }
        ));

        $credential = $credentials->first();

        if ($credential) {
            $firstAccountUrl = $credential->getAccount()->getData()->getAccountUrl();
            return $this->getDoctrine()->getRepository('App:Accounts')->findForAuth($firstAccountUrl, $user);
        } else {
            // If all else fails, return null
            return null;
        }
    }

    /**
     * @param array|int $access
     * @param Users|null $user - jesli nie ma token-a w headers @see exports
     * @param Accounts|null $account - operacja na koncie
     * @return bool
     */
    public function can($access = [], Users $user = null, Accounts $account = null): bool
    {
        $user = $user ? $user : $this->user();

        if ($account) {
            if (!$credential = $user->getCredential($account)) {
                if ($this->access($user) === Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
                    $em = $this->getDoctrine()->getManager();

                    $user->addAccount($account);

                    // virtual credentials
                    $credential = new Credentials();
                    $credential
                        ->setAccount($account)
                        ->setUser($user)
                        ->setEnabled(true)
                        ->setAccess(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])
                        ->setVirtual(true);

                    $em->persist($credential);
                    $em->flush();
                } else {
                    $this->logSecurityViolation($user, [
                        'account_id'   => $account->getId(),
                        'account_name' => $account->getOrganizationName(),
                        'access_level' => $access
                    ]);
                    return false;
                }
            } else {
                // SA moze wszystko nawet jak ma nizsze uprawnienia na danym koncie...
                // important: after virtual access
                if ($this->access() === Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
                    return true;
                }
            }

            $_access = $credential->isEnabled() ? $credential->getAccess() : 0;
        } else {
            $_access = $this->access($user);
        }

        if (is_array($access)) {
            if (!in_array($_access, $access)) {
                $this->logSecurityViolation($user, [
                    'access_level' => $access
                ]);
                return false;
            }
        } else {
            if ($_access < (int)$access) {
                $this->logSecurityViolation($user, [
                    'access_level' => $access
                ]);
                return false;
            }
        }

        return true;
    }

    public function getTimeZones()
    {
        return $this->getParameter('timezones');
    }

    public function dateFormat($user = null)
    {
        $config = $this->getParameter('timezones');
        $user = $user ? $user : $this->user();
        return $user->getData()->getTimeZone() ? $config[$user->getData()->getTimeZone()]['dateFormat'] : 'MM/DD/YYYY';
    }

    public function phpDateFormat($user = null)
    {
        $config = $this->getParameter('timezones');
        $user = $user ? $user : $this->user();
        return $user->getData()->getTimeZone() ? $config[$user->getData()->getTimeZone()]['phpDateFormat'] : 'm/d/Y';
    }

    protected function logSecurityViolation($user, $data)
    {
        $eventDispatcher = $this->get('App\Service\EventDispatcherFactoryService')->getEventDispatcher();
        $eventDispatcher->dispatch(
            new UserSecurityViolationEvent($user, null, 'Security violation', $data),
            UserSecurityViolationEvent::class
        );
    }

    /**
     * @throws AuthException
     */
    protected function verifyAccess(?int $accessLevel = null): void
    {
        if ($this->checkToken() === false) {
            throw AuthException::invalidTokenException();
        }

        if (null !== $accessLevel && $this->access() < $accessLevel) {
            throw AuthException::noAccess();
        }
    }
}
