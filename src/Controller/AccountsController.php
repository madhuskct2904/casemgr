<?php

namespace App\Controller;

use App\Entity\Accounts;
use App\Entity\Credentials;
use App\Entity\Programs;
use App\Entity\ReportFolder;
use App\Entity\Users;
use App\Entity\UsersData;
use App\Entity\UsersSettings;
use App\Enum\AccountType;
use App\Enum\ParticipantType;
use App\Event\UserSwitchedAccountEvent;
use App\Exception\ExceptionMessage;
use App\Service\AccountService;
use App\Utils\Helper;
use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use Exception;
use Nucleos\UserBundle\Util\TokenGenerator;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use function Sentry\captureException;

/**
 * Class AccountsController
 *
 * @IgnoreAnnotation("api")
 * @IgnoreAnnotation("apiGroup")
 * @IgnoreAnnotation("apiHeader")
 * @IgnoreAnnotation("apiParam")
 * @IgnoreAnnotation("apiSuccess")
 * @IgnoreAnnotation("apiError")
 *
 * @package App\Controller
 */
class AccountsController extends Controller
{
    private $roles = [
        1 => 'REFERRAL USER',
        2 => 'VOLUNTEER',
        3 => 'CASE MANAGER',
        4 => 'SUPERVISOR',
        5 => 'PROGRAM ADMINISTRATOR',
        6 => 'SYSTEM ADMINISTRATOR',
    ];

    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * @return JsonResponse
     * @api {post} /accounts Get all Accounts
     * @apiGroup Accounts
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} search Keyword
     *
     * @apiSuccess {Array} accounts Results
     * @apiSuccess {String} search Keyword
     *
     * @apiError message Error Message
     *
     */
    public function indexAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $access = $this->access();

        if (!$this->can(Users::ACCESS_LEVELS['SUPERVISOR']) && !$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $params = $this->getRequest()->params();
        $search = isset($params['search']) ? $params['search'] : null;

        $qb = $this->getDoctrine()->getRepository('App:Accounts')->findForIndex($search, $this->user(), $access)->getQuery();

        $adapter = new DoctrineORMAdapter($qb);
        $pagerfanta = new Pagerfanta($adapter);

        // pagination and sorting on frontend
        $pagerfanta->setMaxPerPage(999);
        $pagerfanta->setCurrentPage(1);

        $accounts = [];
        foreach ($pagerfanta->getCurrentPageResults() as $k => $result) {
            $accounts[] = $this->accountToArray($result);
        }

        return $this->getResponse()->success([
            'accounts' => $accounts,
            'search' => $search
        ]);
    }

    private function accountToArray(Accounts $account, $all = false, $includeChildren = false)
    {
        $array = [
            'id' => $account->getId(),
            'organizationName' => $account->getOrganizationName(),
            'systemId' => $account->getSystemId(),
            'accountType' => $account->getAccountType(),
            'activationDate' => $account->getActivationDate(),
            'status' => $account->getStatus(),
            'default' => $this->user()->getDefaultAccount() === $account->getData()->getAccountUrl(),
            'city' => $account->getData()->getCity(),
            'state' => $account->getData()->getState(),
            'twilioPhone' => $account->getTwilioPhone(),
            'twilioStatus' => $account->isTwilioStatus(),
            'HIPAARegulated' => (int)$account->isHIPAARegulated(),
            'accountOwner' => $account->getData()->getAccountOwner(),
            'projectContact' => $account->getData()->getProjectContact(),
            'main' => $account->isMain(),
            'participantType' => $account->getParticipantType(),
            'parentAccount' => $account->getAccountType() == 'child' ? $this->accountToArray($account->getParentAccount()) : null,
            'linkedHistorical' => $this->formatHistoricalEntries($account->getLinkedAccountHistory()),
            'searchInOrganizations' => json_decode($account->getSearchInOrganizations(), true),
            'twoFactorAuthEnabled' => (int)$account->isTwoFactorAuthEnabled()
        ];

        if ($includeChildren) {
            $array['childrenAccounts'] = $account->getAccountType() == 'parent' ? $this->accountsListToArray($account->getChildrenAccounts()) : null;
        }

        if ($account->getAccountType() == AccountType::PROGRAM) {
            foreach ($account->getPrograms() as $program) {
                $array['programs'][] = ['id' => $program->getId(), 'name' => $program->getName()];
            }
        }

        $em = $this->getDoctrine()->getManager();

        if ($all) {
            // users
            $array['users'] = [];
            foreach ($account->getUsers() as $user) {
                if ($user->isUser() && ($credential = $user->getCredential($account)) && !$credential->isVirtual()) {
                    $log = $em->getRepository('App:UsersActivityLog')->findOneBy([
                        'user' => $user,
                        'account' => $account,
                        'eventName' => 'user.login_success'
                    ], ['dateTime' => 'DESC']);

                    $lastLogin = '';

                    if ($log) {
                        $lastLogin = $log->getDateTime()->format('Y-m-d H:i:s');
                    }

                    if (!$log && $user->getLastLogin()) {
                        $lastLogin = $user->getLastLogin()->format('Y-m-d H:i:s');
                    }

                    $array['users'][] = [
                        'id' => $user->getId(),
                        'fullName' => $user->getData() ? $user->getData()->getFullName() : '',
                        'email' => $user->getEmail(),
                        'lastLogin' => $lastLogin,
                        'access' => $credential->getAccess(),
                        'enabled' => $credential->isEnabled(),
                        'confirmed' => $user->getConfirmationToken() && $user->getPasswordRequestedAt() === null ? false : true,
                    ];
                }
            }

            // data
            $array['data'] = [];
            if ($data = $account->getData()) {
                $array['data'] = [
                    'address1' => $data->getAddress1(),
                    'address2' => $data->getAddress2(),
                    'city' => $data->getCity(),
                    'state' => $data->getState(),
                    'country' => $data->getCountry(),
                    'zipCode' => $data->getZipCode(),
                    'contactName' => $data->getContactName(),
                    'emailAddress' => $data->getEmailAddress(),
                    'phoneNumber' => $data->getPhoneNumber(),
                    'accountUrl' => $data->getAccountUrl(),
                    'accountOwner' => $data->getAccountOwner(),
                    'projectContact' => $data->getProjectContact(),
                    'billingEmailAddress' => $data->getBillingEmailAddress(),
                    'billingContactName' => $data->getBillingContactName(),
                    'billingPrimaryPhone' => $data->getBillingPrimaryPhone(),
                    'serviceCategory' => $data->getServiceCategory()
                ];
            }
        }

        return $array;
    }

    private function formatHistoricalEntries($linkedAccountsHistory)
    {
        $list = [];
        foreach ($linkedAccountsHistory as $item) {
            $data = json_decode($item->getData(), true);

            $list[] = [
                'id' => $data['id'],
                'organizationName' => $data['organizationName'],
                'systemId' => $data['systemId'],
                'activationDate' => $data['activationDate'],
                'twilioPhone' => $data['twilioPhone'],
                'twilioStatus' => $data['twilioStatus'],
                'projectContact' => $data['data']['projectContact'],
                'accountOwner' => $data['data']['accountOwner'],
                'isHistorical' => true
            ];
        }

        return $list;
    }

    public function accountsListToArray($accounts)
    {
        $list = [];
        foreach ($accounts as $account) {
            $list[] = $this->accountToArray($account, false);
        }
        return $list;
    }

    /**
     * @return JsonResponse
     * @api {post} /accounts/:type/create Create Account
     * @apiGroup Accounts
     *
     * @apiHeader {String} token Authorization Token
     *
     * @apiSuccess {String} message Success Message
     * @apiSuccess {Integer} id Account Id
     *
     * @apiError message Error Message
     * @apiError errors Form Errors
     *
     */
    public function createAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $account = new Accounts();

        $form = $this->createForm('App\Form\AccountsType', $account);

        $params = $this->getRequest()->params();

        if (isset($params['twilioPhone'])) {
            $params['twilioPhone'] = Helper::convertPhone($params['twilioPhone']);
        } else {
            $params['twilioPhone'] = null;
        }

        if (isset($params['searchInOrganizations'])) {
            $searchInOrganizationsStr = json_encode($params['searchInOrganizations']);

            if (json_last_error() == JSON_ERROR_NONE) {
                $params['searchInOrganizations'] = $searchInOrganizationsStr;
            }
        }

        if (isset($params['parentId'])) {
            $parentAccount = $this->getDoctrine()->getRepository('App:Accounts')->find($params['parentId']);
            $account->setParentAccount($parentAccount);
            unset($params['parentId']);
        }

        $form->submit($params);

        if (!$form->isValid()) {
            return $this->getResponse()->validation([
                'errors' => Helper::getFormErrors($form)
            ]);
        }

        // unique SystemId
        for ($i = 0; $i < 10; $i++) {
            $systemId = Helper::generateCode();
            if (!$this->getDoctrine()->getRepository('App:Accounts')->findOneBy([
                'systemId' => $systemId
            ])) {
                break;
            }
        }

        $account->setAccountType($params['accountType']);
        $account->setSystemId(isset($systemId) ? $systemId : uniqid());
        $account->setActivationDate(new \DateTime());

        // unique AccountUrl
        $domain = $this->getParameter('frontend_domain');
        $name = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $account->getOrganizationName())));
        $accountUrl = rtrim(sprintf('%s%s', $name ? $name : 'noname', '.' . $domain), '.');
        if ($this->getDoctrine()->getRepository('App:AccountsData')->findOneBy([
            'accountUrl' => $accountUrl
        ])) {
            $accountUrl = sprintf(
                '%s%s%s',
                strtolower($account->getOrganizationName()),
                strtolower($account->getSystemId()),
                '.' . $domain
            );
        }

        $data = $account->getData();
        $data->setAccountUrl($accountUrl);

        $em = $this->getDoctrine()->getManager();
        $em->persist($account);
        $em->flush();

        $reportsFolder = new ReportFolder();
        $reportsFolder->setName('account' . $account->getId());
        $em->persist($reportsFolder);
        $em->flush();


        if (isset($parentAccount) && $parentAccount instanceof Accounts) {
            $modulesKeys = [];

            if ($account->getParticipantType() == ParticipantType::MEMBER) {
                $modulesKeys = $this->getParameter('modules')['member_forms']['core'];
            }

            if ($account->getParticipantType() == ParticipantType::INDIVIDUAL) {
                $modulesKeys = $this->getParameter('modules')['participant_forms']['core'];
            }

            $forms = $parentAccount->getForms();
            foreach ($forms as $form) {
                if (!$form->getModule()) {
                    continue;
                }

                if (in_array($form->getModule()->getKey(), $modulesKeys)) {
                    $form->addAccount($account);
                }
            }

            $this->getDoctrine()->getManager()->flush();
        }

        return $this->getResponse()->success([
            'message' => 'Account created.',
            'id' => $account->getId()
        ]);
    }

    /**
     * @param Request $request
     * @param $id
     * @return JsonResponse
     * @api {post, get} /accounts/:type/edit/:id Edit Account
     * @apiGroup Accounts
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String="casemgr","referral"}} :type Account Type
     * @apiParam {Integer} :id Account Id
     *
     * @apiSuccess {String} message Success Message
     * @apiSuccess {Array} account Result
     *
     * @apiError message Error Message
     * @apiError errors Form Errors
     *
     */
    public function editAction(Request $request, $id)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->getDoctrine()->getRepository('App:Accounts')->findOneBy([
            'id' => $id
        ]);

        if (!$this->can(Users::ACCESS_LEVELS['SUPERVISOR'], null, $account)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        if ($account === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_ACCOUNT);
        }

        if ($request->isMethod('POST')) {
            if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'], null, $account)) {
                return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
            }

            $form = $this->createForm('App\Form\AccountsType', $account);

            $params = $this->getRequest()->params();

            if (isset($params['twilioPhone'])) {
                $params['twilioPhone'] = Helper::convertPhone($params['twilioPhone']);
            } else {
                $params['twilioPhone'] = null;
            }

            if (isset($params['searchInOrganizations'])) {
                $searchInOrganizationsStr = json_encode($params['searchInOrganizations']);

                if (json_last_error() == JSON_ERROR_NONE) {
                    $params['searchInOrganizations'] = $searchInOrganizationsStr;
                }
            }

            $form->submit($params);

            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->flush();

                return $this->getResponse()->success([
                    'message' => 'Account updated.'
                ]);
            }

            return $this->getResponse()->validation([
                'errors' => Helper::getFormErrors($form)
            ]);
        }

        return $this->getResponse()->success([
            'account' => $this->accountToArray($account, true, true)
        ]);
    }

    /**
     * @param $aid
     * @return JsonResponse
     * @api {post} /accounts/:type/create/:aid Add User to Account
     * @apiGroup Accounts
     *
     * @apiHeader {String} token Authorization Token
     *
     * @apiParam {String="casemgr","referral"}} :type Account Type
     * @apiParam {Integer} :aid Account Id
     * @apiParam {String} fullName User Full Name
     * @apiParam {String} email  User Email
     * @apiParam {Number{1..6}} access User Access
     *
     * @apiSuccess {String} message Success Message
     * @apiSuccess {Number} id User Id
     *
     * @apiError message Error message
     * @apiError errors Form Errors
     *
     */
    public function createUserAction($aid)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->getDoctrine()->getRepository('App:Accounts')->findOneBy([
            'id' => $aid
        ]);

        if ($account === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_ACCOUNT);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'], null, $account)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $params = $this->getRequest()->params();

        $em = $this->getDoctrine()->getManager();

        $existingUser = $this
            ->getDoctrine()
            ->getRepository('App:Users')
            ->findOneBy([
                'email' => $params['email']
            ]);

        if (!$existingUser) {
            $user = new Users();
            $user->setEmail($params['email']);
            $user->setPlainPassword(Helper::generateCode(
                8,
                'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz123456789'
            ));
            $user->setUsername(strtolower(Helper::generateCode(4)) . uniqid());
            $user->setTypeAsUser();
            $user->setEnabled(true);
            $user->setUserDataType(ParticipantType::INDIVIDUAL);
            $user->addAccount($account);
            $user->setDefaultAccount($account->getData()->getAccountUrl());
            $tokenGenerator = new TokenGenerator();
            $user->setConfirmationToken($tokenGenerator->generateToken());

            $form = $this->createForm('App\Form\AccountsUserType', $user);
            $form->submit($params);

            if ($form->isValid()) {
                $em->persist($user);
                $em->flush();

                $userData = new UsersData();

                $fullName = explode(' ', $params['fullName']);

                if (count($fullName) === 3) {
                    $first_name = sprintf('%s %s', $fullName[0], $fullName[1]);
                    $last_name = $fullName[2];
                } else {
                    $first_name = isset($fullName[0]) ? $fullName[0] : '';
                    $last_name = isset($fullName[1]) ? $fullName[1] : '';
                }

                $userData->setFirstName($first_name);
                $userData->setLastName($last_name);

                $userData->setGender(''); // not null ???
                $userData->setSystemId(strtolower(Helper::generateCode(9)));
                $userData->setTimeZone('Etc/GMT+7');
                $userData->setUser($user);

                $em->persist($userData);
                $em->flush();

                // User settings
                $setting = new UsersSettings();

                $setting->setUser($user);
                $setting->setName('widgets');
                $setting->setValue('{"widgetsFirst":[{"id":"1","name":"ProfileParticipant"},{"id":"4","name":"ActivitiesAndServices"}],"widgetsSecond":[{"id":"3","name":"Assingment"},{"id":"2","name":"AssessmentAndOutcomes"}],"widgetsThird":[{"id":"5","name":"CaseNotes"}],"widgetsStash":[{"id":"6","name":"ProfileParticipant"}]}');

                $em->persist($setting);
                $em->flush();

                // Users Accounts Credentials
                $credential = new Credentials();

                // PA cant create SA
                $access = isset($params['access'])
                    ? ((int)$params['access'] > $this->access() ? $this->access() : (int)$params['access'])
                    : 1;

                $credential
                    ->setAccount($account)
                    ->setUser($user)
                    ->setEnabled(true)
                    ->setAccess($access);

                $em->persist($credential);
                $em->flush();

                $em->refresh($user);

                $subject = 'You have been invited to join CaseMGR';

                try {
                    $message = (new TemplatedEmail())
                        ->subject($subject)
                        ->from($this->getParameter('mailer_from'))
                        ->to($user->getEmail())
                        ->htmlTemplate('Emails/new_account.html.twig')
                        ->textTemplate('Emails/new_account.txt.twig')
                        ->context([
                            'title' => $subject,
                            'user' => $user,
                            'account' => $account,
                            'mainUrl' => $this->getParameter('frontend_domain')
                        ]);

                    $this->mailer->send($message);

                    return $this->getResponse()->success([
                        'message' => 'User created.',
                        'id' => $user->getId()
                    ]);
                } catch (Exception $e) {
                    captureException($e); // capture exception by Sentry
                    return $this->getResponse()->error(ExceptionMessage::DEFAULT);
                }
            } else {
                return $this->getResponse()->validation([
                    'errors' => Helper::getFormErrors($form)
                ]);
            }
        } else {
            // user exists
            $isVirtual = false;
            if ($existingUser->getAccounts()->contains($account)) {
                // w viewAs moglismy juz dodac Account z wirtualnym Credential
                // (English: "in viewAs we could already add an Account with a virtual Credential")
                if ($credential = $existingUser->getCredential($account)) {
                    $isVirtual = $credential->isVirtual();
                }

                // User is a member of this Account and has no virtual credentials
                if (!$isVirtual) {
                    return $this->getResponse()->error(ExceptionMessage::UNABLE_TO_ADD_USER_TO_ACCOUNT);
                }
            }

            $existingUser->addAccount($account);

            $access = isset($params['access'])
                ? ((int)$params['access'] > $this->access() ? $this->access() : (int)$params['access'])
                : 1;

            /* Begin Credentials */
            if (!$isVirtual) {
                $credential = new Credentials();
                $credential
                    ->setAccount($account)
                    ->setUser($existingUser)
                    ->setEnabled(true)
                    ->setAccess($access);
                $em->persist($credential);
            } else {
                $credential
                    ->setVirtual(false)
                    ->setEnabled(true)
                    ->setAccess($access);
            }

            $em->flush();
            /* End Credentials */

            $subject = 'You have been invited to join a new CaseMGR workspace';

            try {
                $message = (new TemplatedEmail())
                    ->subject($subject)
                    ->from($this->getParameter('mailer_from'))
                    ->to($existingUser->getEmail())
                    ->htmlTemplate('Emails/existing_account.html.twig')
                    ->textTemplate('Emails/existing_account.txt.twig')
                    ->context([
                        'title' => $subject,
                        'user' => $existingUser,
                        'account' => $account
                    ]);

                $this->mailer->send($message);

                return $this->getResponse()->success([
                    'message' => 'User added to account.',
                    'id' => $existingUser->getId()
                ]);
            } catch (Exception $e) {
                captureException($e); // capture exception by Sentry

                return $this->getResponse()->error(ExceptionMessage::DEFAULT);
            }
        }
    }

    /**
     * @param $id
     * @param $uid
     * @return JsonResponse
     * @api {post} /accounts/:type/:id/toggle/:uid Enable/Disable User Access
     * @apiGroup Accounts
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String="casemgr","referral"}} :type Account Type
     * @apiParam {Integer} :id Account Id
     * @apiParam {Integer} :uid User Id
     *
     * @apiSuccess {String} message Success Message
     * @apiSuccess {Boolean} enabled User access status
     *
     * @apiError message Error message
     *
     */
    public function toggleUserAction($id, $uid)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $user = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
            'id' => $uid,
            'type' => 'user'
        ]);

        if ($user === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_USER);
        }

        $accounts = $user->getAccounts();
        $hasAccount = null;

        foreach ($accounts as $account) {
            if ($account->getId() === (int)$id) {
                $hasAccount = $account;
                break;
            }
        }

        if (!$hasAccount) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_USER);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'], null, $hasAccount)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $em = $this->getDoctrine()->getManager();

        if (!$credential = $user->getCredential($hasAccount)) {
            $credential = new Credentials();

            $credential
                ->setAccount($hasAccount)
                ->setUser($user)
                ->setEnabled(false);

            $em->persist($credential);
        }

        $credential->setEnabled(!$credential->isEnabled());

        if (!$credential->isEnabled()) {
            if ($session = $this->getDoctrine()->getRepository('App:UsersSessions')
                ->findOneBy(['user' => $uid])) {
                $em->remove($session);
            }
        }

        $em->flush();

        return $this->getResponse()->success([
            'message' => $credential->isEnabled() ? 'User was enabled.' : 'User was disabled.',
            'enabled' => $credential->isEnabled()
        ]);
    }

    /**
     * @param $id
     * @param $uid
     * @return JsonResponse
     * @api {post} /accounts/:type/:id/resend/:uid Resend Activation Email
     * @apiGroup Accounts
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String="casemgr","referral"}} :type Account Type
     * @apiParam {Integer} :id Account Id
     * @apiParam {Integer} :uid User Id
     *
     * @apiSuccess {String} message Success Message
     *
     * @apiError message Error message
     *
     */
    public function resendUserAction($id, $uid)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $user = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
            'id' => $uid
        ]);

        if ($user === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_USER);
        }

        if ($user->getConfirmationToken() === null) {
            return $this->getResponse()->error(ExceptionMessage::ALREADY_CONFIRMED_USER);
        }

        $accounts = $user->getAccounts();
        $account = null;

        foreach ($accounts as $acc) {
            if ($acc->getId() === (int)$id) {
                $account = $acc;
                break;
            }
        }

        if ($account === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_USER);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'], null, $account)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $em = $this->getDoctrine()->getManager();
        $tokenGenerator = new TokenGenerator();

        $user->setConfirmationToken($tokenGenerator->generateToken());

        $em->flush();

        $subject = 'You have been invited to join CaseMGR';

        try {
            $message = (new TemplatedEmail())
                ->subject($subject)
                ->from($this->getParameter('mailer_from'))
                ->to($user->getEmail())
                ->htmlTemplate('Emails/new_account.html.twig')
                ->textTemplate('Emails/new_account.txt.twig')
                ->context([
                    'title' => $subject,
                    'user' => $user,
                    'account' => $account,
                    'mainUrl' => $this->getParameter('frontend_domain')
                ]);

            $this->mailer->send($message);

            return $this->getResponse()->success([
                'message' => 'Activation email resent.'
            ]);
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }
    }

    /**
     * @return array|JsonResponse
     * @api {post} /accounts/view Get User Accounts
     * @apiGroup Accounts
     *
     * @apiHeader {String} token Authorization Token
     *
     * @apiSuccess {String} data Results
     *
     * @apiError message Error message
     *
     */
    public function viewAsAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $data = [];
        $access = $this->access();

        if ($access === Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
            $accounts = $this->getDoctrine()->getRepository('App:Accounts')->findAll();
        } else {
            $accounts = $this->user()->getAccounts();
        }

        foreach ($accounts as $account) {
            $credential = $this->user()->getCredential($account);
            if ($access === Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
                if ((!$credential || ($credential && $credential->isEnabled())) && $account->getStatus() === 'Active') {
                    $data[] = $this->accountToArray($account);
                }
            } else {
                if ($credential && $credential->isEnabled() && $account->getStatus() === 'Active') {
                    $data[] = $this->accountToArray($account);
                }
            }
        }

        return $this->getResponse()->success([
            'data' => $data
        ]);
    }

    /**
     * @return JsonResponse
     * @api {post} /accounts/default Set User Default Account
     * @apiGroup Accounts
     *
     * @apiHeader {String} token Authorization Token
     *
     * @apiParam {Integer} account_id Account Id
     *
     * @apiSuccess {Integer} id Account Id
     *
     * @apiError message Error message
     *
     */
    public function setDefaultAction(EventDispatcherInterface $eventDispatcher)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $id = $this->getRequest()->param('account_id');
        $em = $this->getDoctrine()->getManager();

        $account = $this->getDoctrine()->getRepository('App:Accounts')->findOneBy(['id' => $id]);

        if (!$account) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_ACCOUNT, 404);
        }

        if (!$this->can(Users::ACCESS_LEVELS['VOLUNTEER'], null, $account)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $this->user()->setDefaultAccount($account->getData()->getAccountUrl());

        $em->flush();

        $eventDispatcher->dispatch(
            new UserSwitchedAccountEvent($this->user(), $account, 'Switched account'),
            UserSwitchedAccountEvent::class
        );


        return $this->getResponse()->success([
            'id' => $id
        ]);
    }

    /**
     * @return JsonResponse
     * @api {post} /organization/widgets Update User Settings
     * @apiGroup Accounts
     *
     * @apiHeader {String} token Authorization Token
     *
     * @apiParam {Integer} id Account Id
     * @apiParam {Array} value Settings
     *
     * @apiSuccess {String} message Success Message
     *
     * @apiError message Error message
     *
     */
    public function widgetsAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $accountId = $this->getRequest()->param('id');
        $value = $this->getRequest()->param('value');

        if (!$account = $this->getDoctrine()->getRepository('App:Accounts')->findOneBy(['id' => $accountId])) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_ACCOUNT, 401);
        }

        if ($credential = $this->user()->getCredential($account)) {
            $em = $this->getDoctrine()->getManager();
            $credential->setWidgets(json_encode($value));
            $em->flush();

            return $this->getResponse()->success([
                'message' => 'settings updated.'
            ]);
        } else {
            return $this->getResponse()->error(ExceptionMessage::INVALID_CREDENTIALS, 401);
        }
    }

//    TODO: export also entities

    /**
     * @param Request $request
     * @return JsonResponse|Response
     * @api {get} /accounts/export Export to CSV
     * @apiGroup Accounts
     *
     * @apiHeader {String} token Authorization Token
     *
     * @apiParam {String} search Keyword
     *
     * @apiSuccess {File} Response CSV File
     *
     * @apiError message Error message
     *
     */
    public function exportAction(Request $request)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $access = $this->access();

        if ($access < Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        if ($request->isMethod('GET')) {
            $search = $request->query->get('search', null);

            $accounts = $this->getDoctrine()->getRepository('App:Accounts')
                ->findForIndex($search, $this->user(), $access)->getQuery()->getResult();

            $data[] = [
                'Organization Name',
                'System ID',
                'Parent Account',
                'Account Owner',
                'Project Contact',
                'Activation Date',
                'Status',
                'SMS Status',
                'Twilio Phone Number'
            ];

            foreach ($accounts as $account) {

                if ($account->getActivationDate()) {
                    $activationDate = $this->convertDateTime($this->user(), $account->getActivationDate());
                } else {
                    $activationDate = '';
                }

                $data[] = [
                    '"' . $account->getOrganizationName() . '"',
                    $account->getSystemId(),
                    $account->getParentAccount()
                        ? $account->getParentAccount()->getOrganizationName()
                        : ($account->getAccountType() == 'parent' ? 'Yes' : 'No'),
                    $account->getData()->getAccountOwner(),
                    $account->getData()->getProjectContact(),
                    $activationDate,
                    $account->getStatus(),
                    $account->isTwilioStatus() ? 'Active' : 'Disabled',
                    $account->getTwilioPhone()
                ];
            }

            $file_name = 'Accounts';

            return new Response(
                (Helper::csvConvert($data)),
                200,
                [
                    'Content-Type' => 'application/csv',
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $file_name . '.csv'),
                ]
            );
        }

        return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
    }

    public function exportUsersAction(Request $request)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $access = $this->access();

        if ($access < Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        if ($request->isMethod('GET')) {
            $search = $request->query->get('search', null);

            $accounts = $this->getDoctrine()->getRepository('App:Accounts')
                ->findForIndex($search, $this->user(), $access)->getQuery()->getResult();

            $data[] = [
                '"' . $this->convertDateTime($this->user()) . '"'
            ];

            $data[] = [];

            $data[] = [
                'CaseMgr Account',
                'Full Name',
                'Email Address',
                'Last Login',
                'Access',
                'Status',
                'Parent Account',
                'Account Owner',
            ];

            $roles = [
                1 => 'REFERRAL USER',
                2 => 'VOLUNTEER',
                3 => 'CASE MANAGER',
                4 => 'SUPERVISOR',
                5 => 'PROGRAM ADMINISTRATOR',
                6 => 'SYSTEM ADMINISTRATOR',
            ];

            foreach ($accounts as $account) {
                $name = $account->getOrganizationName();
                $row = $this->accountToArray($account, true);

                foreach ($row['users'] as $user) {

                    $parentOrganizationName = isset($row['parentAccount']) ? $row['parentAccount']['organizationName'] : '';

                    $lastLogin = $this->convertDateTime($this->user(), new \DateTime($user['lastLogin']));

                    $data[] = [
                        '"' . $name . '"',
                        '"' . $user['fullName'] . '"',
                        '"' . $user['email'] . '"',
                        '"' . $lastLogin . '"',
                        '"' . isset($roles[$user['access']]) ? $roles[$user['access']] : '' . '"',
                        '"' . ($user['enabled'] == 1 ? 'Active' : 'Disabled') . '"',
                        '"' . $parentOrganizationName . '"',
                        '"' . $row['data']['accountOwner'] . '"',
                    ];
                }
            }

            $file_name = 'Users';

            return new Response(
                (Helper::csvConvert($data)),
                200,
                [
                    'Content-Type' => 'application/csv',
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $file_name . '.csv'),
                ]
            );
        }

        return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
    }

    public function unlinkAccountAction(AccountService $accountService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $id = $this->getRequest()->param('id');
        $account = $this->getDoctrine()->getRepository('App:accounts')->find($id);

        if (!$account) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_ACCOUNT);
        }

        $user = $this->user();

        try {
            $accountService->unlink($account, $user);
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success(['message' => 'Account unlinked']);
    }

    public function byParticipantTypeAction($participantType)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        if (!ParticipantType::isValidValue((int)$participantType)) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT_TYPE);
        }

        $return = [];

        $accounts = $this->getDoctrine()->getRepository('App:Accounts')->findBy(['participantType' => $participantType]);

        foreach ($accounts as $account) {
            $return[] = [
                'id' => $account->getId(),
                'name' => $account->getOrganizationName()
            ];
        }

        return $this->getResponse()->success(['accounts' => $return]);
    }

    public function getProgramsIndexAction($id): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->getDoctrine()->getRepository('App:Accounts')->find($id);

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'], null, $account)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $programs = array_map(static fn(Programs $program) => [
            'id' => $program->getId(),
            'name' => $program->getName(),
            'creation_date' => $program->getCreationDate(),
            'status' => $program->getStatus()
        ], $account->getPrograms()->toArray());

        return $this->getResponse()->success(['programs' => $programs]);
    }

    public function userAccessAction($userId)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        $user = $this->getDoctrine()->getRepository('App:Users')->find($userId);

        $filterAccounts = false;
        $showAccountsIds = [];

        if ($this->access() < Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
            $currentUserCredentials = $this->user()->getCredentials();
            $filterAccounts = true;
            foreach ($currentUserCredentials as $currentUserCredential) {
                $showAccountsIds[] = $currentUserCredential->getAccount()->getId();
            }
        }

        $userCredentials = $user->getCredentials();
        $activityLog = $this->getDoctrine()->getRepository('App:UsersActivityLog')->findBy([
            'user' => $user,
            'eventName' => ['user.login_success', 'user.switch_account']
        ], ['id' => 'ASC']);

        $rows = [];
        $accountsLastLogin = [];

        foreach ($activityLog as $activityLogEntry) {
            $accountsLastLogin[$activityLogEntry->getAccount()->getId()] = $activityLogEntry->getDateTime();
        }

        foreach ($userCredentials as $credential) {

            if (!$credential->isEnabled()) {
                continue;
            }

            if ($filterAccounts && !in_array($credential->getAccount()->getId(), $showAccountsIds)) {
                continue;
            }

            $rows[] = [
                'account' => $credential->getAccount()->getOrganizationName(),
                'role' => $this->roles[$credential->getAccess()],
                'last_login' => $accountsLastLogin[$credential->getAccount()->getId()] ?? ''
            ];
        }

        return $this->getResponse()->success(['rows' => $rows, 'name' => $user->getData()->getFullName()]);
    }

}
