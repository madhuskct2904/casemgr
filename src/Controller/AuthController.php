<?php


namespace App\Controller;


use App\Entity\Accounts;
use App\Entity\Users;
use App\Entity\UsersSessions;
use App\Enum\ParticipantType;
use App\Event\UserLoginFailureEvent;
use App\Event\UserLoginSuccessEvent;
use App\Event\UserLogoutEvent;
use App\Exception\ExceptionMessage;
use App\Service\AlertsHelper;
use App\Service\GeneralSettingsService;
use App\Service\Auth\TwoFactorAuthService;
use Casemgr\Pii\Pii;
use DateTime;
use Nucleos\UserBundle\Util\TokenGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use function Sentry\captureException;

final class AuthController extends Controller
{
    public function authAction(
        Request $request,
        EncoderFactoryInterface $encoderFactory,
        TwoFactorAuthService $twoFactorAuthService,
        EventDispatcherInterface $eventDispatcher
    )
    {
        if (!$request->isMethod('POST')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD);
        }

        $em = $this->getDoctrine()->getManager();

        $email = $this->getRequest()->param('email');
        $password = $this->getRequest()->param('password');
        $id = $this->getRequest()->param('id');

        $user = ($id !== null and $email === null) ? $this->getDoctrine()->getRepository('App:Users')->find($id) : $this->getDoctrine()->getRepository('App:Users')->findOneBy([
            'email'   => $email,
            'type'    => 'user',
            'enabled' => true
        ]);

        if ($user === null) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_EMAIL);
        }

        $encoder = $encoderFactory->getEncoder($user);

        if (!$encoder->isPasswordValid($user->getPassword(), $password, $user->getSalt())) {
            $twoFactorAuthService->invalidateTwoFactorForUser($user);

            $eventDispatcher->dispatch(
                new UserLoginFailureEvent($user, null, ExceptionMessage::NOT_FOUND_EMAIL_OR_PASSWORD),
                UserLoginFailureEvent::class
            );

            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_EMAIL_OR_PASSWORD);
        }

        if ($user->getConfirmationToken() !== null && $user->getPasswordRequestedAt() === null) {
            $eventDispatcher->dispatch(
                new UserLoginFailureEvent($user, null, ExceptionMessage::UNCONFIRMED_EMAIL),
                UserLoginFailureEvent::class
            );

            return $this->getResponse()->error(ExceptionMessage::UNCONFIRMED_EMAIL);
        }

        $currentAccount = $this->account($user);

        if (!$currentAccount) {
            $eventDispatcher->dispatch(
                new UserLoginFailureEvent($user, $currentAccount, ExceptionMessage::DISABLED_ACCOUNT),
                UserLoginFailureEvent::class
            );

            return $this->getResponse()->error(ExceptionMessage::DISABLED_ACCOUNT);
        }

        // expired password
        $now = new DateTime();
        $expiredTime = clone $user->getPasswordSetAt();
        $expiredTime->modify('+90 days');

        if ($now > $expiredTime) {
            $tokenGenerator = new TokenGenerator();
            $token = $tokenGenerator->generateToken();
            $user->setConfirmationToken($token);
            $user->setPasswordRequestedAt(new DateTime());
            $em->flush();

            return $this->getResponse()->success([
                'confirmation_token' => $token
            ]);
        }

        $this->setUserDefaultAccount($user, $currentAccount);

        if ($currentAccount->isTwoFactorAuthEnabled()) {
            $isValid = $twoFactorAuthService->checkTwoFactorIsValid($user);

            if (!$isValid) {
                $token = $twoFactorAuthService->generateTwoFactorAuthToken($user, $currentAccount);
                $email = $this->anonymizeEmail($user->getEmail());

                return $this->getResponse()->success([
                    'token'                  => $token,
                    'email'                  => $email,
                    'redirect_to_two_factor' => true
                ]);
            }
        }

        $sessionToken = $this->createUserSession($user, $currentAccount);

        $data = $this->createAuthSuccessResponseData($sessionToken, $user, $user->getDefaultAccount());

        $eventDispatcher->dispatch(
            new UserLoginSuccessEvent($user, $currentAccount, 'Login success'),
            UserLoginSuccessEvent::class
        );

        return $this->getResponse()->success($data);
    }

    public function resendTwoFactorCodeAction(
        Request $request,
        TwoFactorAuthService $twoFactorAuthService,
        EventDispatcherInterface $eventDispatcher
    )
    {
        if (!$request->isMethod('POST')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD);
        }

        $token = $this->getRequest()->param('token', null);

        if (!$token) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_USER, 404);
        }

        $user = $twoFactorAuthService->findUser($token);

        if (!$currentAccount = $this->account($user)) {
            $eventDispatcher->dispatch(
                new UserLoginFailureEvent($user, $currentAccount, ExceptionMessage::DISABLED_ACCOUNT),
                UserLoginFailureEvent::class
            );

            return $this->getResponse()->error(ExceptionMessage::DISABLED_ACCOUNT);
        }

        if (!$user) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_USER, 404);
        }

        return $this->getResponse()->success([
            'redirect_to_two_factor' => true,
            'message'                => 'A new code has been sent to your email.',
            'token'                  => $twoFactorAuthService->generateTwoFactorAuthToken($user, $currentAccount)
        ]);
    }

    public function secondFactorAuthAction(
        Request $request,
        TwoFactorAuthService $twoFactorAuthService,
        EventDispatcherInterface $eventDispatcher
    )
    {
        if (!$request->isMethod('POST')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD);
        }

        $code = $this->getRequest()->param('code', null);
        $token = $this->getRequest()->param('token', null);

        if (!$token || !$code) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_USER, 404);
        }

        $user = $twoFactorAuthService->findUser($token);

        if (!$currentAccount = $this->account($user)) {
            $eventDispatcher->dispatch(
                new UserLoginFailureEvent($user, $currentAccount, ExceptionMessage::DISABLED_ACCOUNT),
                UserLoginFailureEvent::class
            );

            return $this->getResponse()->error(ExceptionMessage::DISABLED_ACCOUNT);
        }

        if (!$user) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_USER, 404);
        }

        $status = $twoFactorAuthService->tryAuth($token, $code);

        if ($status === $twoFactorAuthService::CODE_EXPIRED) {
            return $this->getResponse()->success([
                'redirect_to_two_factor' => true,
                'message'                => 'Verification code expired. A new code has been sent to your email.',
                'token'                  => $twoFactorAuthService->generateTwoFactorAuthToken($user, $currentAccount)
            ]);
        }

        if ($status === $twoFactorAuthService::CODE_INVALID) {
            return $this->getResponse()->success([
                'redirect_to_two_factor' => true,
                'message'                => 'Invalid verification code entered. A new code has been sent to your email.',
                'token'                  => $twoFactorAuthService->generateTwoFactorAuthToken($user, $currentAccount)
            ]);
        }

        if ($status !== $twoFactorAuthService::AUTH_SUCCESS) {
            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        $sessionToken = $this->createUserSession($user, $currentAccount);

        $data = $this->createAuthSuccessResponseData($sessionToken, $user, $user->getDefaultAccount());

        $eventDispatcher->dispatch(
            new UserLoginSuccessEvent($user, $currentAccount, 'Login success'),
            UserLoginSuccessEvent::class
        );

        return $this->getResponse()->success($data);
    }

    public function logoutAction(
        TwoFactorAuthService $twoFactorAuthService,
        EventDispatcherInterface $eventDispatcher
    )
    {
        $em = $this->getDoctrine()->getManager();

        $token = $this->getToken();
        $session = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneByToken($token);

        if (!$session) {
            return $this->getResponse()->success();
        }

        $accountUrl = $session->getAccount();
        $user = $session->getUser();

        $twoFactorAuthService->invalidateTwoFactorForUser($user);

        $accountData = $em->getRepository('App:AccountsData')->findOneBy(['accountUrl' => $accountUrl]);

        $account = null;

        if ($accountData) {
            $account = $em->getRepository('App:Accounts')->find($accountData->getId());
        }

        $eventDispatcher->dispatch(
            new UserLogoutEvent($user, $account, 'Logout success'),
            UserLogoutEvent::class
        );

        $em->remove($session);
        $em->flush();

        return $this->getResponse()->success();
    }

    public function checkTokenAction(TwoFactorAuthService $twoFactorAuthService, GeneralSettingsService $generalSettingsService, AlertsHelper $alertsHelper)
    {
        if ($this->checkToken(false)) {
            $isMaintenanceMode = $generalSettingsService->isMaintenanceMode($this->user());

            $session = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneByToken($this->getToken());

            $twoFactorAuthService->extendValidity($this->user());

            return $this->getResponse()->success([
                'maintenance_mode' => $isMaintenanceMode,
                'expired_time'     => $session->getExpiredDate(),
                'current_date'     => new DateTime(),
                'alerts'           => $alertsHelper->getAlerts($this->account(), $this->user(), $this->access())
            ]);
        }

        return $this->getResponse()->error(ExceptionMessage::INVALID_USER_SESSION);
    }

    private function getUserOrganizations($user): array
    {
        $currentAccount = $this->account($user);

        $modules = [];

        if ($currentAccount->getParticipantType() == ParticipantType::INDIVIDUAL) {
            $modules = $this->getParameter('modules')['participant_forms'];
        }

        if ($currentAccount->getParticipantType() == ParticipantType::MEMBER) {
            $modules = $this->getParameter('modules')['member_forms'];
        }

        $organizations[] = [
            'id'            => $currentAccount->getId(),
            'modules'       => $modules,
            'systemId'      => $currentAccount->getSystemId(),
            'name'          => $currentAccount->getOrganizationName(),
            'type'          => $currentAccount->getAccountType(),
            'url'           => $currentAccount->getData()->getAccountUrl(),
            'parentAccount' => $currentAccount->getParentAccount()
                ? [
                    'id'   => $currentAccount->getParentAccount()->getId(),
                    'name' => $currentAccount->getParentAccount()->getOrganizationName()
                ]
                : []
        ];

        foreach ($user->getAccounts() as $account) {
            if ($account !== $currentAccount) {
                $organizations[] = [
                    'id'       => $account->getId(),
                    'systemId' => $account->getSystemId(),
                    'name'     => $account->getOrganizationName()
                ];
            }
        }

        return $organizations;
    }

    /**
     * @param $user
     * @return array
     */
    private function getUserSettings($user): array
    {
        $userSettings = $user->getSettings();
        $settings = [];

        if ($userSettings !== null) {
            foreach ($userSettings as $setting) {
                $settings[$setting->getName()] = $setting->getValue();
            }
        }
        return $settings;
    }

    private function createUserSession($user, Accounts $currentAccount): string
    {
        $em = $this->getDoctrine()->getManager();

        $session = $em->getRepository('App:UsersSessions')->findOneByUser($user);

        if ($session === null) {
            $session = new UsersSessions();
        }

        $time = new DateTime();
        $expired = clone $time;

        $token = Pii::generateRandomString(32);

        $expired->modify('+30 minutes');

        $session->setToken($token);
        $session->setUser($user);
        $session->setCreatedDate($time);
        $session->setExpiredDate($expired);
        $session->setLastActionDate($time);
        $session->setAccount($currentAccount->getData()->getAccountUrl());

        $user->setLastLogin(new DateTime());

        $em->persist($session);
        $em->flush();

        return $token;
    }

    private function anonymizeEmail(string $email): string
    {
        $emailFragments = explode('@', $email);

        $starsCount = strlen($emailFragments[1]) - 2;
        $domain = substr($emailFragments[1], 0, 1) . str_repeat('*', $starsCount) . substr($emailFragments[1], -1, 1);

        if (strlen($emailFragments[0]) == 1) {
            $name = $emailFragments[0] . '*@';
        }

        if (strlen($emailFragments[0]) == 2) {
            $name = substr($emailFragments[0], 0, 1) . '*@';
        }

        if (strlen($emailFragments[0]) > 2) {
            $starsCount = strlen($emailFragments[0]) - 2;
            $name = substr($emailFragments[0], 0, 2) . str_repeat('*', $starsCount) . '@';
        }

        return $name.$domain;
    }

    private function setUserDefaultAccount(Users $user, Accounts $currentAccount): void
    {
        $em = $this->getDoctrine()->getManager();

        if ($currentAccount->getData()->getAccountUrl() !== $user->getDefaultAccount()) {
            $user->setDefaultAccount($currentAccount->getData()->getAccountUrl());
            $em->flush();
        }
    }

    private function createAuthSuccessResponseData(string $sessionToken, $user, $defaultAccount): array
    {
        $data = [
            'token'                  => $sessionToken,
            'first_name'             => $user->getData()->getFirstName(),
            'last_name'              => $user->getData()->getLastName(),
            'email'                  => $user->getEmail(),
            'gender'                 => $user->getData()->getGender(),
            'system_id'              => $user->getData()->getSystemId(),
            'case_manager'           => $user->getData()->getCaseManager(),
            'secondary_case_manager' => $user->getData()->getCaseManagerSecondary(),
            'phone_number'           => $user->getData()->getPhoneNumber(),
            'avatar'                 => $user->getData()->getAvatar(),
            'date_birth'             => $user->getData()->getDateBirth() ? $user->getData()->getDateBirth()->format('d.m.Y') : '',
            'job_title'              => $user->getData()->getJobTitle(),
            'time_zone'              => $user->getData()->getTimeZone(),
            'organizations'          => $this->getUserOrganizations($user),
            'settings'               => $this->getUserSettings($user),
            'access'                 => $this->access($user),
            'default_account'        => $defaultAccount ?? null
        ];

        return $data;
    }
}
