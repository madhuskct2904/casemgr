<?php

namespace App\Service\Auth;

use App\Entity\Accounts;
use App\Entity\UserAuth;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Util\TokenGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Twig\Environment;

final class TwoFactorAuthService
{
    private $em;
    private $mailer;
    private $senders;
    private $twig;

    const AUTH_SUCCESS = 'auth_success';
    const CODE_EXPIRED = 'code_expired';
    const CODE_INVALID = 'code_invalid';
    const AUTH_ENTRY_INVALID = 'auth_entry_invalid';

    public function __construct(EntityManagerInterface $em, MailerInterface $mailer, array $emailSenders, Environment $twig)
    {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->senders = $emailSenders;
        $this->twig = $twig;
    }

    /**
     * Token is used for identify unique 2FA entry.
     */
    public function generateTwoFactorAuthToken(Users $user, Accounts $accounts): string
    {
        $userAuth = $this->em->getRepository(UserAuth::class)->findOneBy(['user' => $user]);

        if (!$userAuth) {
            $userAuth = new UserAuth();
            $userAuth->setAccount($accounts);
            $userAuth->setBrowserFingerprint($this->generateBrowserFingerprint());
            $userAuth->setEmailSent(false);
            $userAuth->setUser($user);
            $this->em->persist($userAuth);
            $this->em->flush();
        }

        $code = str_pad(rand(0, 999999), 6, 0, STR_PAD_LEFT);

        $userAuth->setAccount($accounts);
        $userAuth->setBrowserFingerprint($this->generateBrowserFingerprint());
        $userAuth->setEmaiLSent(false);
        $userAuth->setUpdatedAt(new \DateTime());
        $userAuth->setCode($code);

        $tokenGenerator = new TokenGenerator();

        $token = $tokenGenerator->generateToken();
        $userAuth->setToken($token);

        $this->em->flush();

        try {
            $this->sendCodeEmail($userAuth->getUser(), $code, $userAuth->getAccount());
            $userAuth->setEmailSent(true);
        } catch (Exception $e) {
            $userAuth->setEmailSent(false);
            $this->em->flush();
        }

        return $token;
    }

    public function extendValidity(Users $user)
    {
        $userAuth = $this->em->getRepository(UserAuth::class)->findOneBy(['user' => $user]);

        if ($userAuth) {
            $userAuth->setUpdatedAt(new \DateTime());
            $this->em->flush();
        }
    }

    public function invalidateTwoFactorForUser(Users $user)
    {
        $userAuthEntries = $this->em->getRepository(UserAuth::class)->findBy(['user' => $user]);

        if ($userAuthEntries) {
            foreach ($userAuthEntries as $userAuthEntry) {
                $this->em->remove($userAuthEntry);
            }

            $this->em->flush();
        }
    }

    public function checkTwoFactorIsValid(Users $user)
    {
        $userAuth = $this->em->getRepository(UserAuth::class)->findOneBy(['user' => $user]);

        if (!$userAuth) {
            return false;
        }

        $now = new \DateTime();

        if (!$userAuth->getUpdatedAt() || ($now->diff($userAuth->getUpdatedAt())->h > 24)) {
            return false;
        }

        return true;
    }

    public function tryAuth(string $token, string $code): string
    {
        $userAuth = $this->em->getRepository(UserAuth::class)->findOneBy(['token' => $token]);

        if (!$userAuth) {
            return self::AUTH_ENTRY_INVALID;
        }

        $now = new \DateTime();

        // for checking authentication - code is valid for 2 minutes
        if ($now->diff($userAuth->getUpdatedAt())->i > 2) {
            return self::CODE_EXPIRED;
        }

        if ($code !== $userAuth->getCode()) {
            return self::CODE_INVALID;
        }

        $userAuth->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return self::AUTH_SUCCESS;
    }

    public function findUser(string $token): ?Users
    {
        $userAuth = $this->em->getRepository(UserAuth::class)->findOneBy(['token' => $token]);

        if ($userAuth) {
            return $userAuth->getUser();
        }

        return null;
    }

    private function generateBrowserFingerprint(): string
    {
        return md5($_SERVER['HTTP_USER_AGENT']);
    }

    private function sendCodeEmail(Users $user, string $code, ?Accounts $accounts)
    {
        $organizationName = $accounts ? $accounts->getOrganizationName() : 'CaseMGR';
        $subject = 'Verification Code requested for ' . $organizationName;
        $header = 'Two-Step Verification';
        $HTMLBody = "Hello " . $user->getData()->getFullName(false) . ",<br/><br/>Your verification code is <strong>" . $code . "</strong>.";
        $txtBody = strip_tags($HTMLBody);

        $senders = $this->senders;
        $sender = $senders['support'];
        $senderEmail = $sender['email'];
        $senderName = $sender['name'];
        $senderTitle = $senderName;
        $senderAddress = new Address($senderEmail, $senderName);

        $message = (new TemplatedEmail())
            ->subject($subject)
            ->from($senderAddress)
            ->replyTo($senderAddress)
            ->to($user->getEmail())
            ->htmlTemplate('Emails/system_email.html.twig')
            ->textTemplate('Emails/system_email.txt.twig')
            ->context([
                'title'          => $subject,
                'header'         => $header,
                'content'        => $HTMLBody,
                'textContent'    => $txtBody,
                'senderTitle'    => $senderTitle,
                'recipientEmail' => $user->getEmail()
            ]);

        $this->mailer->send($message);
    }

}
