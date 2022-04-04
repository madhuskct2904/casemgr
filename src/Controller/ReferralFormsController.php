<?php

namespace App\Controller;

use App\Domain\Form\FormConditionsRender;
use App\Domain\ReferralForms\ReferralEntryCreator;
use App\Entity\Accounts;
use App\Entity\Forms;
use App\Entity\FormsData;
use App\Entity\FormsValues;
use App\Entity\Referral;
use App\Entity\Users;
use App\Enum\AccountType;
use App\Enum\ReferralStatus;
use App\Event\FormCreatedEvent;
use App\Event\ReferralNotEnrolledEvent;
use App\Exception\ExceptionMessage;
use App\Service\GeneralSettingsService;
use App\Service\Referrals\ReferralHelper;
use App\Service\Referrals\ReferralFeedWidgetHelper;
use App\Service\Referrals\NewParticipantHelper;
use App\Service\S3ClientFactory;
use App\Utils\Helper;
use Aws\S3\Exception\S3Exception;
use Exception;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use ReCaptcha\ReCaptcha;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function Sentry\captureException;

class ReferralFormsController extends Controller
{
    public function getByUidAction($uid, GeneralSettingsService $generalSettingsService)
    {
        try {
            $maintenance = $generalSettingsService->getMaintenanceMode();
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        if (!$form = $this->getDoctrine()->getRepository('App:Forms')->findOneBy([
            'shareUid' => $uid,
            'publish'  => 1
        ])) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM_ID, 404);
        }

        $captchaEnabled = false;

        if ($form->isCaptchaEnabled()) {
            $captchaPublic = $this->getParameter('captcha_public');
            $captchaEnabled = true;
        }

        $accounts = $form->getAccounts();

        $destAccounts = [];

        foreach ($accounts as $account) {
            $destAccounts[] = $account->getOrganizationName();
        }

        sort($destAccounts);

        return $this->getResponse()->success([
            'name'                   => $form->getName(),
            'description'            => $form->getDescription(),
            'data'                   => $form->getData(),
            'type'                   => $form->getType(),
            'conditionals'           => $form->getConditionals(),
            'calculations'           => $form->getCalculations(),
            'hide_values'            => $form->getHideValues() ? $form->getHideValues() : "[]",
            'maintenance'            => $maintenance,
            'captcha_public'         => $captchaPublic ?? '',
            'captcha_enabled'        => $captchaEnabled,
            'destination_accounts'   => $destAccounts,
            'extra_validation_rules' => $form->getExtraValidationRules()
        ]);
    }


    public function saveAction(
        ReferralEntryCreator $referralEntryCreator,
        EventDispatcherInterface $eventDispatcher,
        S3ClientFactory $s3ClientFactory
    )
    {
        $allData = $this->getRequest()->post('data');
        $allData = str_replace(array("\r","\t","\n"), array('\r','\t','\n'), $allData);
        $allData = json_decode($allData, true);

        $data = $allData['data'];

        if ($data === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARAMETERS, 422);
        }

        $uid = $allData['uid'];

        $form = $this->getDoctrine()->getRepository('App:Forms')->findOneBy(['shareUid' => $uid]);

        if (!$form) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM_ID, 422);
        }

        if ($form->isCaptchaEnabled()) {
            $captcha = new ReCaptcha($this->getParameter('captcha_secret'));
            $captchaResponse = $captcha->verify($allData['captchaVerifyCode']);

            if (!$captchaResponse->isSuccess()) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_CAPTCHA, 422);
            }
        }

        $module = $form->getModule();

        if (!$module) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_MODULE, 422);
        }

        $account = $this->getDestinationAccount($data, $form);

        $formsData = new FormsData();
        $formsData->setAccount($account);
        $formsData->setModule($module);
        $formsData->setForm($form);
        $formsData->setElementId(0);

        $user = $this->user();

        if ($user) {
            $formsData->setEditor($user);
            $formsData->setCreator($user);
        }

        $formsData->setUpdatedDate(new \DateTime());
        $formsData->setCreatedDate(new \DateTime());

        $em = $this->getDoctrine()->getManager();

        $em->persist($formsData);
        $em->flush();

        $files = $this->getRequest()->files();

        foreach ($files as $fieldId => $file) {
            $explode = explode('-', $fieldId);

            unset($explode[count($explode) - 1]);

            $fieldId = implode('-', $explode);

            $dataIdx = array_search($fieldId, array_column($data, 'name'));

            if ($dataIdx === false) {
                continue;
            }

            $uploaded = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['size']);
            $fileName = sprintf(
                '%s.%s',
                md5(time() . $fieldId . $formsData->getId() . $file['name'] . mt_rand()),
                $uploaded->guessExtension()
            );
            $client = $s3ClientFactory->getClient();
            $bucket = $this->getParameter('aws_bucket_name');
            $prefix = $this->getParameter('aws_forms_folder');


            try {
                $client->putObject([
                    'Bucket'     => $bucket,
                    'Key'        => $prefix . '/' . $fileName,
                    'SourceFile' => $file['tmp_name'],
                    //'ACL'           => 'public-read'
                ]);
            } catch (S3Exception $e) {
//            todo: response with error
            }


            $data[$dataIdx]['value'] = json_encode([
                [
                    'name' => $file['name'],
                    'file' => $fileName
                ]
            ]);
        }

        foreach ($data as $row) {
            $currentValue = new FormsValues();
            $currentValue->setData($formsData);

            if (!isset($row['name'], $row['value'])) {
                continue;
            }

            if ((strpos($row['name'], 'signature-') === false) && substr($row['value'], 0, 22) === 'data:image/png;base64,') {
                $img = str_replace('data:image/png;base64,', '', $row['value']);
                $fileName = sprintf('%s.png', md5(time() . $formsData->getId()));
                $decodedImage = base64_decode($img);

                if ((base64_encode(base64_decode($img, true))) === $img) {
                    $client = $s3ClientFactory->getClient();
                    $bucket = $this->getParameter('aws_bucket_name');
                    $prefix = $this->getParameter('aws_forms_folder');

                    try {
                        $client->putObject([
                            'Bucket' => $bucket,
                            'Key'    => $prefix . '/' . $fileName,
                            'Body'   => $decodedImage,
                            'ACL'    => 'public-read'
                        ]);
                    } catch (S3Exception $e) {
                    }

                    $row['value'] = $fileName;
                }
            }

            if ((strpos($row['name'], 'text-') !== false) || strpos($row['name'], 'textarea-') !== false) {
                $row['value'] = trim($row['value']);
            }

            $currentValue->setName($row['name']);
            $currentValue->setValue($row['value']);
            $currentValue->setDate(new \DateTime());

            $em = $this->getDoctrine()->getManager();

            $em->persist($currentValue);
            $em->flush();
        }

        $em->refresh($formsData);

        // Activity Feed
        $eventDispatcher->dispatch(new FormCreatedEvent($formsData), FormCreatedEvent::class);

        $referralEntry = $referralEntryCreator->referralFilled($formsData);
        $dataToken = $referralEntry->getSubmissionToken();

        return $this->getResponse()->success([
            'data_token' => $dataToken,
            'message'    => 'Your referral has been submitted! For any questions, please contact <strong>' . $account->getOrganizationName() . '</strong> directly.'
        ]);
    }

    public function getIndexGroupedAction(ReferralFeedWidgetHelper $referralFeedWidgetHelper)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['CASE_MANAGER']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        $account = $this->account();

        return $this->getResponse()->success([
            'referrals' => $referralFeedWidgetHelper->prepareReferralFeedWidgetData($account)
        ]);
    }

    public function getByIdAction($referralId)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['CASE_MANAGER']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 404);
        }

        $referral = $this->getDoctrine()->getRepository('App:Referral')->find($referralId);

        return $this->getResponse()->success(['referral' => $this->getReferralAsArray($referral)]);
    }

    public function getForNewParticipantAction($referralId, NewParticipantHelper $newParticipantHelper)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $values = $newParticipantHelper->getDataForNewParticipant($referralId);
        $userData = $newParticipantHelper->getUserDataForNewParticipant($referralId);

        return $this->getResponse()->success(['values' => $values, 'user_data' => $userData]);
    }

    public function setNotEnrolledAction(EventDispatcherInterface $eventDispatcher)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $referralId = $this->getRequest()->param('referral_id');

        if ($referralId) {
            $eventData = [
                'referral_id' => $referralId,
                'comment'     => $this->getRequest()->param('comment'),
                'user'        => $this->user()
            ];

            $eventDispatcher->dispatch(new ReferralNotEnrolledEvent($eventData), ReferralNotEnrolledEvent::class);
        }

        $em = $this->getDoctrine()->getManager();
        $referral = $em->getRepository('App:Referral')->find($referralId);

        if ($referral) {
            return $this->getResponse()->success(['referral' => $this->getReferralAsArray($referral)]);
        }

        return $this->getResponse()->error(ExceptionMessage::INVALID_DATA);
    }


    public function getIndexAction(Request $request)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $params = $request->query->all();

        $from = null;
        $to = null;

        if (isset($params['from']) && (bool)strtotime($params['from'])) {
            $from = new \DateTime($params['from']);
        }

        if (isset($params['to']) && (bool)strtotime($params['to'])) {
            $to = new \DateTime($params['to']);
        }

        $page = isset($params['page']) ? ((int)$params['page'] > 0 ? (int)$params['page'] : 1) : 1;

        $qb = $this->getDoctrine()->getRepository('App:Referral')->findForAccount($this->account(), $from, $to);

        $adapter = new DoctrineORMAdapter($qb);
        $pagerfanta = new Pagerfanta($adapter);

        $pagerfanta->setMaxPerPage(8);
        $pagerfanta->setCurrentPage($page);

        $referrals = [];

        foreach ($pagerfanta->getCurrentPageResults() as $row) {
            $referrals[] = $this->getReferralAsArray($row);
        }

        return $this->getResponse()->success([
            'referrals' => $referrals,
            'page'      => $pagerfanta->getCurrentPage(),
            'pages'     => $pagerfanta->getNbPages(),
            'total'     => $pagerfanta->getNbResults(),
            'from'      => $from ? $from->getTimestamp() : null,
            'to'        => $to ? $to->getTimestamp() : null
        ]);
    }

    public function exportFeedAsCsvAction(Request $request, ReferralHelper $referralHelper)
    {
        if (!$request->isMethod('GET')) {
            $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
        }

        $token = $request->query->get('token');

        if (!$session = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneBy(['token' => $token])) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 404);
        }

        $user = $session->getUser();
        $account = $this->account($session->getUser());
        $params = $request->query->all();

        $from = null;
        $to = null;

        if (isset($params['from']) && (bool)strtotime($params['from'])) {
            $from = new \DateTime($params['from']);
        }

        if (isset($params['to']) && (bool)strtotime($params['to'])) {
            $to = new \DateTime($params['to']);
        }

        $referrals = $this->getDoctrine()->getRepository('App:Referral')->findForAccount($account, $from, $to)->getResult();

        $tmp = fopen('php://temp', 'r+');

        $exportDate = new \DateTime();
        $exportDate = $exportDate->format($this->phpDateFormat($user) . ' h:i A');

        fputcsv($tmp, [$exportDate]);
        fputcsv($tmp, ['', '', '']);
        fputcsv($tmp, ['Participant Name', 'Activity Message', 'Date']);

        $timeOffset = Helper::getTimezoneOffset($user->getData()->getTimeZone());

        foreach ($referrals as $referral) {
            $participantName = $referralHelper->getParticipantName($referral);

            if ($referral->getLastActionAt()) {
                $date = $referral->getLastActionAt()->modify(sprintf("%+d", $timeOffset * -1) . ' hours')->format($this->phpDateFormat($user) . ' h:i A');
            } else {
                $date = $referral->getCreatedAt()->modify(sprintf("%+d", $timeOffset * -1) . ' hours')->format($this->phpDateFormat($user) . ' h:i A');
            }

            if ($referral->getStatus() == ReferralStatus::PENDING) {
                fputcsv($tmp, [$referralHelper->getParticipantName($referral, true, ', '), 'Referral received for: ' . $participantName . ' on ' . $date, $date]);
            }

            if ($referral->getStatus() == ReferralStatus::ENROLLED) {
                fputcsv($tmp, [$referral->getEnrolledParticipant() ? $referral->getEnrolledParticipant()->getData()->getFullName() : '', 'Referral completed for: ' . $participantName . ' on ' . $date, $date]);
            }

            if ($referral->getStatus() == ReferralStatus::NOT_ENROLLED) {
                fputcsv($tmp, [$referralHelper->getParticipantName($referral, true, ', '), 'Referral completed for: ' . $participantName . ' on ' . $date, $date]);
            }
        }

        rewind($tmp);

        $out = '';

        while ($line = fgets($tmp)) {
            $out .= $line;
        }

        return new Response(
            $out,
            200,
            [
                'Content-Type'        => 'application/csv',
                'Content-Disposition' => 'attachment;filename="referrals.csv"'
            ]
        );
    }

    public function downloadPdfAction(Request $request, FormConditionsRender $formConditionsRender)
    {
        if (!$request->isMethod('GET')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 404);
        }

        $submissionToken = $request->query->get('id');
        $em = $this->getDoctrine()->getManager();
        $referral = $em->getRepository('App:Referral')->findOneBy(['submissionToken' => $submissionToken]);

        if (!$referral) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 404);
        }

        $formData = $referral->getFormData();

        if (!$formData) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 404);
        }

        $data = $formConditionsRender->setFormData($formData)->renderData();

        foreach ($data as $idx => $dataItem) {
            if ($dataItem['type'] === 'row') {
                unset($data[$idx]);
            }
        }

        $html = $this->renderView('single-form-pdf.html.twig', [
            'formName' => $referral->getFormData()->getForm()->getName(),
            'data'     => $data,
            'date'     => $referral->getLastActionAt()
        ]);

        $fileName = 'referral_form';

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName . '.pdf')
            ]
        );
    }

    private function getReferralAsArray(Referral $referral): array
    {
        $referralHelper = $this->get('App\Service\Referrals\ReferralHelper');
        $participantName = $referralHelper->getParticipantName($referral);

        return [
            'id'                    => $referral->getId(),
            'status'                => $referral->getStatus(),
            'comment'               => $referral->getComment(),
            'participant_name'      => $participantName,
            'data_id'               => $referral->getFormData()->getId(),
            'last_action_user_name' => $referral->getLastActionUser() ? $referral->getLastActionUser()->getData()->getFullName() : '',
            'last_action_at'        => $referral->getLastActionAt(),
            'participant_id'        => $referral->getEnrolledParticipant() ? $referral->getEnrolledParticipant()->getId() : null,
            'created_at'            => $referral->getCreatedAt()
        ];
    }

    protected function getDestinationAccount(array $data, Forms $form): Accounts
    {
        $formAccounts = $form->getAccounts();
        $defaultAccountType = $formAccounts[0]->getAccountType();

        if (in_array($defaultAccountType, [AccountType::PROGRAM, AccountType::DEFAULT])) {
            return $formAccounts[0];
        }

        foreach ($data as $field) {
            if (isset($field['name'], $field['value']) && strpos($field['name'], 'destination-account-') !== false) {
                $accountName = $field['value'];
                $destAccount = $this->getDoctrine()->getRepository('App:Accounts')->findOneBy([
                    'organizationName' => $accountName
                ]);

                if ($destAccount) {
                    if (!$formAccounts->contains($destAccount)) {
                        throw new \Exception('Invalid account selected');
                    }
                    return $destAccount;
                }
            }
        }
    }
}
