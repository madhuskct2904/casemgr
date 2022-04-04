<?php
namespace App\Handler;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class SessionIdleHandler
 *
 * @package App\Handler
 */
class SessionIdleHandler
{
    protected $session;
    protected $securityToken;
    protected $router;
    protected $maxIdleTime;

    /**
     * SessionIdleHandler constructor.
     *
     * @param SessionInterface $session
     * @param TokenStorageInterface $securityToken
     * @param RouterInterface $router
     * @param int $maxIdleTime
     */
    public function __construct(SessionInterface $session, TokenStorageInterface $securityToken, RouterInterface $router, int $maxIdleTime = 0)
    {
        $this->session 			= $session;
        $this->securityToken 	= $securityToken;
        $this->router 			= $router;
        $this->maxIdleTime 		= $maxIdleTime;
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST != $event->getRequestType()) {
            return;
        }

        if ($this->maxIdleTime > 0) {
            $this->session->start();

            $lapse = time() - $this->session->getMetadataBag()->getLastUsed();

            if ($lapse > $this->maxIdleTime) {
                $this->securityToken->setToken(null);
                $this->session->getFlashBag()->set('info', 'You have been logged out due to inactivity.');
                //$event->setResponse(new RedirectResponse($this->router->generate('fos_user_security_login')));
            }
        }
    }
}
