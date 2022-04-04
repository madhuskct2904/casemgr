<?php

namespace App\Controller;

use App\Entity\Credentials;
use App\Entity\Users;
use App\Entity\UserAuth;
use App\Entity\UsersSessions;
use App\Enum\ParticipantType;
use App\Event\ParticipantRemovedEvent;
use App\Event\UserActivityEvent;
use App\Exception\ExceptionMessage;
use App\Service\AlertsHelper;
use App\Service\CaseloadWidgetService;
use App\Service\CloneParticipantProfileDataService;
use App\Service\GeneralSettingsService;
use App\Service\S3ClientFactory;
use App\Utils\Helper;
use Aws\S3\Exception\S3Exception;
use Casemgr\Pii\Pii;
use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use Exception;
use Nucleos\Util\TokenGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use function Sentry\captureException;
use DateTime;

/**
 * Class UsersController
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
class UsersController extends Controller
{

    public function dataAction(
        GeneralSettingsService $generalSettingsService,
        AlertsHelper $alertsHelper,
        CaseloadWidgetService $caseloadWidgetService
    )
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $user = $this->user();

        // Users settings
        $userSettings = $user->getSettings();
        $settings = [];

        if ($userSettings !== null) {
            foreach ($userSettings as $setting) {
                $settings[$setting->getName()] = $setting->getValue();
            }
        }

        $isMaintenanceMode = $generalSettingsService->isMaintenanceMode($user);

        $data = [
            'maintenance_mode'       => $isMaintenanceMode,
            'email'                  => $user->getEmail(),
            'system_id'              => $user->getData()->getSystemId(),
            'case_manager'           => $user->getData()->getCaseManager(),
            'secondary_case_manager' => $user->getData()->getCaseManagerSecondary(),
            'phone_number'           => $user->getData()->getPhoneNumber(),
            'avatar'                 => $user->getData()->getAvatar(),
            'job_title'              => $user->getData()->getJobTitle(),
            'time_zone'              => $user->getData()->getTimeZone(),
            'date_format'            => $this->dateFormat($user),
            'settings'               => $settings,
            'organization_id'        => $user->getData()->getOrganizationId(),
            'status'                 => $user->getData()->getStatus(),
            'status_label'           => $user->getData()->getStatusLabel(),
            'first_name'             => $user->getData()->getFirstName(),
            'last_name'              => $user->getData()->getLastName(),
            'id'                     => $user->getId()
        ];

        $currentAccount = $this->account();

        $modules = [];

        if ($currentAccount->getParticipantType() == ParticipantType::INDIVIDUAL) {
            $modules = $this->getParameter('modules')['participant_forms'];
        }

        if ($currentAccount->getParticipantType() == ParticipantType::MEMBER) {
            $modules = $this->getParameter('modules')['member_forms'];
        }

        // Data for account dashboard
        $organizations[] = [
            'id'            => $this->account($user)->getId(),
            'systemId'      => $this->account($user)->getSystemId(),
            'modules'       => $modules,
            'name'          => $this->account($user)->getOrganizationName(),
            'accountType'   => $this->account()->getAccountType(),
            'url'           => $this->account()->getData()->getAccountUrl(),
            'twilioStatus'  => $this->account()->getTwilioStatus(),
            'parentAccount' => $currentAccount->getParentAccount()
                ? [
                    'id'   => $currentAccount->getParentAccount()->getId(),
                    'name' => $currentAccount->getParentAccount()->getOrganizationName()
                ]
                : []
        ];

        foreach ($user->getCredentials() as $credential) {
            if ($credential->isEnabled() && $credential->getAccount() !== $this->account()) {
                $organizations[] = [
                    'id'       => $credential->getAccount()->getId(),
                    'systemId' => $credential->getAccount()->getSystemId(),
                    'name'     => $credential->getAccount()->getOrganizationName()
                ];
            }

            if ($credential->getAccount()->getData()->getAccountUrl() === $user->getDefaultAccount()) {
                $defaultAccount = $credential->getAccount()->getId();
            }
        }

        $data['default_account'] = isset($defaultAccount) ? $defaultAccount : null;
        $data['organizations'] = $organizations;

        $data['access'] = $this->access();
        $data['twilioStatus'] = $this->account()->isTwilioStatus() ? true : false;


        // Caseload summary widget
        if ($data['access'] >= Users::ACCESS_LEVELS['CASE_MANAGER']) {
            $data['access'] == Users::ACCESS_LEVELS['CASE_MANAGER']
                ? $data['caseload_summary'] = $caseloadWidgetService->getCaseloadWidgetDataForManager($user, $this->account())
                : $data['caseload_summary'] = $caseloadWidgetService->getCaseloadWidgetTotals($this->account());

            // Top 5 reports
            $data['top_reports'] = [];

            $topReportsSettingsKey = 'top_reports_account_' . $this->account()->getId();

            if (isset($settings[$topReportsSettingsKey]) && count($topReportsIds = json_decode($settings[$topReportsSettingsKey]))) {
                $topReports = $this->getDoctrine()->getRepository('App:Reports')->findWhereIdIn($topReportsIds);
                foreach ($topReports as $topReport) {
                    $data['top_reports'][] = ['name' => $topReport->getName(), 'id' => $topReport->getId()];
                }
            }
        }

        // Activity feed widget
        $data['activity_feed'] = $this->getDoctrine()->getRepository('App:ActivityFeed')->getWidget($user, $data['access'], $this->account());

        // Dashboard widgets
        $credential = $this->user()->getCredential($this->account());
        $credentialWidgets = json_decode($credential->getWidgets(), true);

        if (json_last_error() === JSON_ERROR_NONE) {
            foreach ($credentialWidgets as $key => $credWidgets) {
                $credentialWidgets[$key] = array_values($credWidgets);
            }
            $data['organization_widgets'] = $credentialWidgets;
        } else {
            $data['organization_widgets'] = null;
        }

        $allWidgets = $this->getParameter('organization_widgets');

        if ($this->access() < Users::ACCESS_LEVELS['CASE_MANAGER']) {
            $allWidgets = array_filter($allWidgets, function ($widget) {
                return $widget['name'] !== 'ReferralFeed';
            });
        }

        $data['all_widgets'] = $allWidgets;
        $data['alerts'] = $alertsHelper->getAlerts($currentAccount, $user, $this->access());

        return $this->getResponse()->success($data);
    }

    /**
     * @TODO co to jest ?
     *
     * @return mixed
     */
    public function passwordAction(EncoderFactoryInterface $encoderFactory)
    {
        $em = $this->getDoctrine()->getManager();

        $email = $this->getRequest()->param('email');
        $user = $this->getDoctrine()->getRepository('App:Users')->findOneByEmail($email);

        if ($user !== null) {
            // @todo Plugin sending email
            $password = Pii::generateRandomString(15);

            /** @var \Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder $encoder */
            $encoder = $encoderFactory->getEncoder($user);

            $user->setPassword(
                $encoder->encodePassword(
                    $password,
                    $user->getSalt()
                )
            );

            $em->persist($user);
            $em->flush();

            return $this->getResponse()->success();
        }

        return $this->getResponse()->error(ExceptionMessage::WRONG_EMAIL);
    }

    /**
     * @return mixed
     * @api {post} /users/avatar Change Avatar
     * @apiGroup Users
     *
     * @apiHeader {String} token Authorization Token
     *
     * @apiSuccess {String} name File Name
     *
     * @apiError message Error Message
     *
     */
    public function avatarAction(S3ClientFactory $s3ClientFactory)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $em = $this->getDoctrine()->getManager();
        $user_id = $this->getRequest()->param('user_id');
        $assignment_id = $this->getRequest()->param('assignment_id', null);
        $file = $this->getRequest()->param('file');

        if ($user_id !== null) {
            $user = $this->getDoctrine()->getRepository('App:Users')->find($user_id);
        } else {
            $user = $this->user();
        }

        if ($user === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_USER);
        }

        $file_name = sprintf('%s.png', md5(time() . $user_id));

        $assignment = null;
        if ($assignment_id) {
            if ($assignment = $this->getDoctrine()->getRepository('App:Assignments')->findOneBy(['id' => $assignment_id])) {
                $file_name = $assignment_id . '-' . $file_name;
            } else {
                return $this->getResponse()->error(ExceptionMessage::INVALID_ASSIGNMENT);
            }
        }

        if ($assignment !== null) {
            $previousAvatar = $assignment->getAvatar();
        } else {
            $previousAvatar = $user->getData()->getAvatar();
        }

        $client = $s3ClientFactory->getClient();
        $bucket = $this->getParameter('aws_bucket_name');
        $prefix = $this->getParameter('aws_avatars_folder');

        if ($file) {
            $encoded_image = explode(",", $file)[1];
            $decoded_image = base64_decode($encoded_image);

            try {
                $client->putObject([
                    'Bucket' => $bucket,
                    'Key'    => $prefix . '/' . $file_name,
                    'Body'   => $decoded_image,
                    'ACL'    => 'public-read'
                ]);
            } catch (S3Exception $e) {
                captureException($e); // capture exception by Sentry

                return $this->getResponse()->error(ExceptionMessage::DEFAULT);
            }
        } else {
            // clear avatar
            $file_name = null;
        }

        if ($assignment !== null) {
            $assignment->setAvatar($file_name);
        } else {
            $user->getData()->setAvatar($file_name);
        }

        $em->flush();


        if ($previousAvatar !== null) {
            try {
                $client->deleteObject([
                    'Bucket' => $bucket,
                    'Key'    => $prefix . '/' . $previousAvatar
                ]);
            } catch (S3Exception $e) {
                //dump($e->getMessage()); // do nothing file not exists
            }
        }

        return $this->getResponse()->success(['name' => $file_name]);
    }

    /**
     * @param Request $request
     *
     * @return mixed
     * @api {get,post} /users/profile/edit Edit Profile
     * @apiGroup Users
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer}  user_id User Id
     * @apiParam {Integer}  account_id Account Id
     * @apiParam {String}  job_title Job Title
     * @apiParam {String}  phone_number Phone Number
     * @apiParam {String}  full_name Full Name
     * @apiParam {String}  email Email
     *
     * @apiSuccess {String} message Success Message
     * @apiSuccess {Integer} [id] User Id
     * @apiSuccess {String} [fullName] User Full Name
     * @apiSuccess {String} [email] User Email
     * @apiSuccess {String} [jobTitle] User Job Title
     * @apiSuccess {String} [primaryPhone] User Phone
     * @apiSuccess {String} [avatar] User Avatar
     * @apiSuccess {String} [access] User Level Access
     *
     * @apiError message Error Message
     *
     */
    public function editAction(Request $request)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $params = $this->getRequest()->params();
        $userId = isset($params['user_id']) && $params['user_id'] ? (int)$params['user_id'] : (int)$request->query->get('user_id');
        $accountId = isset($params['account_id']) && $params['account_id'] ? (int)$params['account_id'] : (int)$request->query->get('account_id');

        if ($userId) {
            $user = $this->getDoctrine()->getRepository('App:Users')->findOneBy(['id' => $userId]);

            if ($user === null) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_USER);
            }

            if ($request->isMethod('GET')) {
                if ($user->getData()) {
                    $credential = $this->getDoctrine()->getRepository('App:Credentials')->findOneBy([
                        'user'    => $userId,
                        'account' => $accountId
                    ]);

                    $data = [
                        'id'           => $user->getId(),
                        'fullName'     => $user->getData()->getFullName(false),
                        'email'        => $user->getEmail(),
                        'jobTitle'     => $user->getData()->getJobTitle(),
                        'primaryPhone' => $user->getData()->getPhoneNumber(),
                        'avatar'       => $user->getData()->getAvatar(),
                        'access'       => $credential ? $credential->getAccess() : 0
                    ];
                } else {
                    $data = [];
                }

                return $this->getResponse()->success($data);
            }
        } else {
            $user = $this->user();
        }

        $em = $this->getDoctrine()->getManager();

        $job_title = $this->getRequest()->param('job_title');
        $phone_number = $this->getRequest()->param('phone_number');
        $full_name = $this->getRequest()->param('full_name');
        $email = $this->getRequest()->param('email');

        if (empty($email) === false and !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_EMAIL);
        }
        if ($userEmail = $this->getDoctrine()->getRepository('App:Users')->findOneBy(['email' => $email])) {
            if ($user->getId() !== $userEmail->getId()) {
                return $this->getResponse()->error(ExceptionMessage::NOT_UNIQUE_EMAIL);
            }
        }

        if (empty($email) === false) {
            $user->setEmail($email);
        }

        if ($job_title !== null) {
            $user->getData()->setJobTitle($job_title);
        }

        if ($phone_number !== null) {
            $user->getData()->setPhoneNumber($phone_number);
        }

        if ($full_name !== null) {
            $name = explode(' ', $full_name);

            if (count($name) === 3) {
                $first_name = sprintf('%s %s', $name[0], $name[1]);
                $last_name = $name[2];
            } else {
                $first_name = isset($name[0]) ? $name[0] : '';
                $last_name = isset($name[1]) ? $name[1] : '';
            }

            $user->getData()->setFirstName($first_name);
            $user->getData()->setLastName($last_name);
        }

        $em->persist($user);
        $em->flush();

        return $this->getResponse()->success(['Changes saved.']);
    }

    /**
     * @return mixed
     * @api {post} /users/profile/password Change Password
     * @apiGroup Users
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} password Password
     * @apiParam {String} new_password New Password
     *
     * @apiSuccessExample {json} Success-Response:
     *  { "data": "Password changed." }
     *
     * @apiError message Error Message
     *
     */
    public function changePasswordAction(EncoderFactoryInterface $encoderFactory)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $params = $this->getRequest()->params();
        if (isset($params['user_id']) && $params['user_id']) {
            $user = $this->getDoctrine()->getRepository('App:Users')->findOneBy(['id' => $params['user_id']]);

            if ($user === null) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_USER);
            }
        } else {
            $user = $this->user();
        }

        $em = $this->getDoctrine()->getManager();

        $password = $this->getRequest()->param('password');
        $new_password = $this->getRequest()->param('new_password');
        $old_password = $user->getPassword();
        $encoder = $encoderFactory->getEncoder($user);

        if ($error = Helper::validatePassword(
            $new_password,
            $user->getData()->getFirstName(),
            $user->getData()->getLastName(),
            $old_password,
            $encoder
        )) {
            return $this->getResponse()->error($error);
        }

        /** @var \Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder $encoder */
        $encoder = $encoderFactory->getEncoder($user);

        if ($encoder->isPasswordValid($user->getPassword(), $password, $user->getSalt())) {
            if ($new_password) {
                $user->setPassword(
                    $encoder->encodePassword(
                        $new_password,
                        $user->getSalt()
                    )
                );

                $em->persist($user);
                $em->flush();

                return $this->getResponse()->success(['Password changed.']);
            }
        }

        return $this->getResponse()->error(ExceptionMessage::INVALID_PASSWORD);
    }

    /**
     * @return mixed
     * @api {post} /users/timezone Set Time Zone
     * @apiGroup Users
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} time_zone Choices time zone
     *
     * @apiSuccessExample {json} Success-Response:
     *  { "data": "Changes saved." }
     *
     * @apiError message Error Message
     *
     */
    public function timeZoneAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $em = $this->getDoctrine()->getManager();
        $user = $this->user();

        $time_zone = $this->getRequest()->param('time_zone');

        $user->getData()->setTimeZone($time_zone);

        $em->persist($user);
        $em->flush();

        return $this->getResponse()->success(['Changes saved. Logging out in 3 seconds...']);
    }

    /**
     * @return mixed
     * @api {post} /users/settings Settings
     * @apiGroup Users
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} name Name
     * @apiParam {String} value Value
     *
     * @apiSuccessExample {json} Success-Response:
     *  { "data": "settings action" }
     *
     * @apiError message Error Message
     *
     */
    public function settingsAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $name = $this->getRequest()->param('name');
        $value = $this->getRequest()->param('value');

        $user = $this->user();

        $this->getDoctrine()->getRepository('App:UsersSettings')->save($user, $name, $value);

        return $this->getResponse()->success(['settings action']);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @api {post} /confirmation/check Check Confirmation Token
     * @apiGroup Users
     *
     * @apiParam {String} token Confirmation Token
     *
     * @apiError message Error Message
     *
     */
    public function checkConfirmationAction(Request $request)
    {
        if ($request->isMethod('POST')) {
            $params = $this->getRequest()->params();
            $token = (isset($params['token']) && $params['token']) ? $params['token'] : '';

            if ((strlen($token) >= 40) && $user = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                    'confirmationToken' => $token
                ])) {

                $now = new DateTime();
                $requested = clone $user->getPasswordRequestedAt();

                //Expiry time 2 hours set
                if ($requested->modify('+2 hours') < $now) {
                    return $this->getResponse()->error(ExceptionMessage::INVALID_ACTIVATION_LINK);
                }else{
                    return $this->getResponse()->success();
                }
            }
        }
        
        return $this->getResponse()->error(ExceptionMessage::INVALID_ACTIVATION_LINK);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @api {post} /confirmation Confirm Email and Set Password
     * @apiGroup Users
     *
     * @apiParam {String} token Confirmation Token
     * @apiParam {String} password Password
     *
     * @apiSuccess {String} message Success Message
     * @apiSuccess {Integer} id User Id
     *
     * @apiError message Error Message
     *
     */
    public function confirmationAction(Request $request)
    {
        if ($request->isMethod('POST')) {
            $params = $this->getRequest()->params();
            $password = (isset($params['password']) && $params['password']) ? $params['password'] : '';
            $token = (isset($params['token']) && $params['token']) ? $params['token'] : '';

            if (strlen($token) < 40) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_ACTIVATION_LINK);
            }

            $user = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                'confirmationToken' => $token
            ]);

            if ($user) {
                if ($error = Helper::validatePassword(
                    $password,
                    $user->getData()->getFirstName(),
                    $user->getData()->getLastName()
                )) {
                    return $this->getResponse()->error($error);
                }

                $user->setConfirmationToken(null);
                $user->setPlainPassword($password);

                $em = $this->getDoctrine()->getManager();
                $em->flush();

                return $this->getResponse()->success([
                    'message' => 'User confirmed',
                    'id'      => $user->getId()
                ]);
            } else {
                return $this->getResponse()->error(ExceptionMessage::INVALID_ACTIVATION_LINK);
            }
        }

        return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @api {post} /users/delete Delete Participant
     * @apiGroup Users
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} user_id User Id
     *
     * @apiSuccessExample {json} Success-Response:
     *  { "data": "Participant deleted." }
     *
     * @apiError message Error Message
     *
     */
    public function deleteAction(
        Request $request,
        EncoderFactoryInterface $encoderFactory,
        EventDispatcherInterface $eventDispatcher
    )
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['CASE_MANAGER']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        if (!$request->isMethod('POST')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD);
        }

        $user = $this->user();
        $password = $this->getRequest()->param('password');
        $encoder = $encoderFactory->getEncoder($user);

        if ($encoder->isPasswordValid($user->getPassword(), $password, $user->getSalt()) === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PASSWORD);
        }

        $params = $this->getRequest()->params();
        $userId = isset($params['user_id']) && $params['user_id'] ? (int)$params['user_id'] : null;

        if (!$userId) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT);
        }

        $participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
            'id'   => $userId,
            'type' => 'participant'
        ]);

        if (!$participant) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
            $account = $this->account();
            if (!$participant->getAccounts()->contains($account)) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT);
            }
        }

        $em = $this->getDoctrine()->getManager();

        $formsData = $this->getDoctrine()->getRepository('App:FormsData')->findBy([
            'element_id' => $participant->getId()
        ]);

        $invalidateFormsIds = []; // for reports invalidation

        foreach ($formsData as $fd) {
            $invalidateFormsIds[] = $fd->getForm()->getId();
            $em->remove($fd);
        }

        $this->getDoctrine()->getRepository('App:ReportsForms')->invalidateForms(array_unique($invalidateFormsIds));

        $activityFeed = $this->getDoctrine()->getRepository('App:ActivityFeed')->findBy([
            'participant' => $participant
        ]);

        foreach ($activityFeed as $activityFeedEntry) {
            $em->remove($activityFeedEntry);
        }

        $em->remove($participant);

        $eventDispatcher->dispatch(new ParticipantRemovedEvent($participant), ParticipantRemovedEvent::class);

        $em->flush();

        return $this->getResponse()->success(['Participant deleted.']);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @api {post} /users/access Set User Access Level
     * @apiGroup Users
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} user_id User Id
     * @apiParam {Integer} account_id Account Id
     * @apiParam {Integer={1..6}} access User Access Level
     *
     * @apiSuccessExample {json} Success-Response:
     *  { "data": "User access updated." }
     *
     * @apiError message Error Message
     *
     */
    public function accessAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $params = $this->getRequest()->params();
        $userId = isset($params['user_id']) && $params['user_id'] ? (int)$params['user_id'] : null;
        $access = isset($params['access']) && $params['access'] ? (int)$params['access'] : 1;
        $accountId = isset($params['account_id']) && $params['account_id'] ? (int)$params['account_id'] : null;

        if ($userId) {
            $user = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                'id'   => $userId,
                'type' => 'user'
            ]);

            if ($user && $accountId) {
                if (!$account = $user->getAccounts()->filter(
                    function ($entry) use ($accountId) {
                        return $entry->getId() === $accountId;
                    }
                )) {
                    return $this->getResponse()->error(ExceptionMessage::INVALID_ACCOUNT);
                }

                if ($this->access($user) > $this->access() || $access > $this->access()) {
                    return $this->getResponse()->error(ExceptionMessage::NO_ACCESS);
                }

                if (!$credential = $user->getCredential($account->first())) {
                    $credential = new Credentials();
                    $credential->setUser($user);
                    $credential->setAccount($account->first());
                    $credential->setEnabled(true);
                }

                $credential->setAccess($access);

                $em = $this->getDoctrine()->getManager();
                $em->flush();

                return $this->getResponse()->success(['User access updated.']);
            }
        }

        return $this->getResponse()->error(ExceptionMessage::INVALID_USER);
    }


    public function checkPasswordAction(EncoderFactoryInterface $encoderFactory)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $user = $this->user();
        $password = $this->getRequest()->param('password');
        $encoder = $encoderFactory->getEncoder($user);

        if ($encoder->isPasswordValid($user->getPassword(), $password, $user->getSalt())) {
            return $this->getResponse()->success(['Password correct.']);
        }

        return $this->getResponse()->error(ExceptionMessage::INVALID_PASSWORD);
    }

    public function timeZonesIndexAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $timezones = [];

        foreach ($this->getParameter('timezones') as $key => $timezone) {
            $timezone['key'] = $key;
            $timezones[] = $timezone;
        }

        return $this->getResponse()->success(['timezones' => $timezones]);
    }

    public function getForNewParticipantAction(
        $participantId,
        CloneParticipantProfileDataService $cloneParticipantService
    )
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->account();
        $participant = $this->getDoctrine()->getManager()->getRepository('App:Users')->find($participantId);

        if (!$participant) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT_ID);
        }

        try {
            $participantData = $cloneParticipantService->prepareClone($account, $participant);
        } catch (\Exception $e) {
            return $this->getResponse()->error(ExceptionMessage::UNABLE_TO_CLONE_PARTICIPANT);
        }

        return $this->getResponse()->success(['values' => $participantData]);
    }

}
