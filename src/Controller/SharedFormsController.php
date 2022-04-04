<?php

namespace App\Controller;

use App\Domain\Form\FormConditionsRender;
use App\Domain\Form\FormToArrayTransformer;
use App\Domain\Form\SharedFieldsService;
use App\Domain\Form\SharedFormPreviewsHelper;
use App\Entity\SharedForm;
use App\Enum\SharedFormServiceCommunicationChannel;
use App\Event\SharedFormSubmittedEvent;
use App\Domain\SharedForms\SharedFormServiceException;
use App\Exception\ExceptionMessage;
use App\Service\FormDataService;
use App\Service\Forms\FormDataValuesService;
use App\Service\GeneralSettingsService;
use App\Service\SharedFormService;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function Sentry\captureException;

class SharedFormsController extends Controller
{
    public function sendToParticipantAction(SharedFormService $sharedFormService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $formDataId = (int)$this->getRequest()->param('form_data_id');
        $participantId = (int)$this->getRequest()->param('participant_id');
        $via = $this->getRequest()->param('via');

        if (!SharedFormServiceCommunicationChannel::isValidValue($via)) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_COMMUNICATION_CHANNEL);
        }

        try {
            $communicationChannelStrategy = $this->get('app.shared_form_communication_strategy.' . $via);
            $sharedFormService->shareWithParticipant($formDataId, $participantId, $this->account(), $this->user(), $communicationChannelStrategy);
        } catch (SharedFormServiceException $e) {
            return $this->getResponse()->error($e->getMessage());
        }

        return $this->getResponse()->success([
            'message' => 'Message sent!'
        ]);
    }

    public function getByUidAction(
        $uid,
        GeneralSettingsService $generalSettingsService,
        FormDataValuesService $formDataValuesService,
        FormDataService $formDataService,
        SharedFormService $sharedFormService,
        SharedFieldsService $sharedFieldsService,
        SharedFormPreviewsHelper $sharedFormsPreviewsHelper
    )
    {
        try {
            $maintenance = $generalSettingsService->getMaintenanceMode();
        } catch (Exception $e) {
            return $this->getResponse()->error($e->getMessage());
        }

        $sharedForm = $this->getDoctrine()->getRepository('App:SharedForm')->findOneBy([
            'uid' => $uid,
            'status' => SharedForm::STATUS['SENT']
        ]);

        if (!$sharedForm) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_LINK);
        }

        $formData = $sharedForm->getFormData();

        if (!$formData) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_LINK);
        }

        $valuesArr = $formDataValuesService->getValuesForParticipantSubmission($formData);

        $formDataArr = $formDataService->setFormData($formData)->getFormDataAsArr();

        $form = $formData->getForm();

        $formArr = FormToArrayTransformer::getFormAsArr($form);

        $lockedFields = $sharedFormService->getLockedFields($form);

        $sharedFieldsValues = $sharedFieldsService->getSharedFieldsValues($form, $sharedForm->getAccount(), $sharedForm->getParticipantUser()->getId());
        $formPreviews = $sharedFormsPreviewsHelper->getFormPreviews($form, $sharedForm->getAccount(), $sharedForm->getParticipantUser()->getId());

        $formArr['form_previews'] = $formPreviews;
        $valuesArr = array_merge($valuesArr, $sharedFieldsValues);

        return $this->getResponse()->success([
            'form'             => $formArr,
            'values'           => $valuesArr,
            'locked_fields'    => $lockedFields,
            'form_data'        => $formDataArr,
            'maintenance'      => $maintenance
        ]);
    }


    public function submitAction(
        Request $request,
        SharedFormService $sharedFormService,
        EventDispatcherInterface $eventDispatcher
    )
    {
        if (!$request->isMethod('POST')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD);
        }

        $postData = $this->getRequest()->post('data', '[]', true);
        $allData = json_decode($postData, true);
        $data = $allData['data'];
        $uid = $allData['uid'];

        if ($data === null || $uid === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARAMETERS, 422);
        }

        $sharedForm = $this->getDoctrine()->getRepository('App:SharedForm')->findOneBy(['uid' => $uid]);

        if (!$sharedForm || $sharedForm->getStatus() === SharedForm::STATUS['COMPLETED']) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARAMETERS, 422);
        }

        $files = $this->getRequest()->files();

        try {
            $sharedFormService->submitForm($sharedForm, $data, $files);
        } catch (SharedFormServiceException $e) {
            return $this->getResponse()->error($e->getMessage());
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        $this->getDoctrine()->getManager()->refresh($sharedForm);

        // Activity Feed
        $eventDispatcher->dispatch(new SharedFormSubmittedEvent($sharedForm), SharedFormSubmittedEvent::class);

        return $this->getResponse()->success([
            'message' => 'Your form has been submitted! For any questions, please contact <strong>' . $sharedForm->getFormData()->getAccount()->getOrganizationName() . '</strong> directly.',
            'submission_token' => $sharedForm->getSubmissionToken()
        ]);
    }

    public function downloadPdfAction(Request $request, FormConditionsRender $formConditionsRender)
    {
        if (!$request->isMethod('GET')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 404);
        }

        $submissionToken = $request->query->get('id');
        $sharedForm = $this->getDoctrine()->getRepository('App:SharedForm')->findOneBy(['submissionToken' => $submissionToken]);

        if (!$sharedForm) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 404);
        }

        $formData = $sharedForm->getFormData();

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
            'formName' => $sharedForm->getFormData()->getForm()->getName(),
            'data'     => $data,
            'date'     => $sharedForm->getCompletedAt()
        ]);

        $fileName = 'form_submission';

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName . '.pdf')
            ]
        );
    }
}
