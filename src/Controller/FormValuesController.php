<?php

namespace App\Controller;

use App\Entity\FormsData;
use App\Event\FormsValuesCreatedEvent;
use App\Event\FormCreatedEvent;
use App\Event\FormUpdatedEvent;
use App\Event\ReferralEnrolledEvent;
use App\Exception\ExceptionMessage;
use App\Service\Forms\SaveValuesService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class FormValuesController extends Controller
{
    private $output = [];

    public function createAction(
        Request $request,
        SaveValuesService $saveValuesService,
        EventDispatcherInterface $eventDispatcher
    )
    {
        if (!$request->isMethod('POST')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD);
        }

        $postData = $this->getRequest()->post('data', '[]', true);
        $allData = json_decode($postData, true);
        $data = $allData['data'];

        $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($allData['form_id'], $this->account(), $this->access());

        if (!$form) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM, 422);
        }

        $participant = null;
        $participantUserId = $allData['participant_user_id'] ?? null;

        if ($participantUserId) {
            $participant = $this->getDoctrine()->getRepository('App:Users')->find($participantUserId);
        }

        $files = $this->getRequest()->files();

        $participantUserId = $participant ? $participant->getId() : null;
        $force = $allData['force_save'] ?? false; // eg. profile duplicate etc
        $assignmentId = $allData['assignment_id'] ?? null;
        $referralId = $allData['referral_id'] ?? null;
        $skipActivityFeed = $allData['skip_activity_feed'] ?? false;

        $moduleName = $form->getModule()->getKey();

        $handler = null;
        // Modules handlers
        $handlerClass = sprintf(
            'App\\Handler\\Modules\\%sHandler',
            preg_replace_callback('/(?:^|_)(.?)/', static function ($str) {
                return str_replace('_', '', strtoupper($str[0]));
            }, $moduleName)
        );

        if (class_exists($handlerClass)) {
            $handler = $this->container->get($handlerClass);

            $handler->setElementId($participantUserId);
            $handler->setForm($form);
            $handler->set($data);
            $handler->setForce($force);
            $handler->setContainer($this->container);
            $handler->setDataId(null);
            $handler->setAccount($this->account());

            $error = $handler->validate();

            if ($error !== null) {
                return $this->getResponse()->validation($error);
            }

            // Before action
            $handler->before($this->access(), $this->account());
            $output = $handler->output();

            if (isset($output['id'])) {
                $participantUserId = $output['id'];
                $handler->setElementId($participantUserId);
            }
        }

        if ($assignmentId) {
            $assignment = $this->getDoctrine()->getRepository('App:Assignments')->findOneBy(['id' => $assignmentId]);
        } else {
            $assignment = null;
        }

        if (in_array($moduleName, [
                'activities_services',
                'assessment_outcomes',
                'organization_general',
                'organization'
            ]) && !$form->getMultipleEntries()) {
            $formData = $this->getDoctrine()->getRepository('App:FormsData')->findBy([
                'form'       => $form,
                'element_id' => $participantUserId,
                'assignment' => $assignment
            ]);

            if (count($formData)) {
                return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_MULTIPLE_ENTRIES);
            }
        }

        $formData = $saveValuesService->createFormData($form, $this->account(), $this->user(), $participant);
        $formData->setAssignment($assignment);
        $formData->setElementId($participantUserId ?: 0);

        $em = $this->getDoctrine()->getManager();
        $em->persist($formData);
        $em->flush();

        $this->assignCaseManagers($participantUserId, $formData);

        $systemValues = null;

        if ($handler !== null) {
            $systemValues = $handler->systemValues();
        }

        if (is_iterable($systemValues) && count($systemValues)) {
            foreach ($systemValues as $formFieldName => $value) {
                $data[] = ['name' => $formFieldName, 'value' => $value];
            }
        }

        $saveValuesService->storeValues($formData, $data, $files);

        if ($handler !== null) {
            $handler->setDataId($formData->getId());
            $handler->after();
            $this->output += $handler->output();
        }

        $this->output['data_id'] = $formData->getId();

        if (!$skipActivityFeed && in_array($moduleName, ['activities_services', 'assessment_outcomes'])) {
            $eventData = [
                'participant_id' => $participantUserId,
                'template'       => $moduleName,
                'title'          => $form->getName(),
                'template_id'    => $formData->getId(),
                'details'        => ['action' => 'created']
            ];

            $eventDispatcher->dispatch(new FormsValuesCreatedEvent($eventData), FormsValuesCreatedEvent::class);
        }

        if ($referralId) {
            $this->referralEnrolled($participantUserId, $referralId, $formData, $eventDispatcher);
        }

        $em->refresh($formData);

        $eventDispatcher->dispatch(new FormCreatedEvent($formData), FormCreatedEvent::class);

        if(!isset($this->output['message'])) {
            $this->output['message'] = 'Form created!';
        }

        return $this->getResponse()->success($this->output);
    }


    public function updateAction(
        Request $request,
        SaveValuesService $saveValuesService,
        EventDispatcherInterface $eventDispatcher
    )
    {
        if (!$request->isMethod('POST')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD);
        }

        $postData = $this->getRequest()->post('data', '[]', true);
        $allData = json_decode($postData, true);
        $data = $allData['data'];

        $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($allData['form_id'], $this->account(), $this->access());

        if (!$form) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM, 422);
        }

        $files = $this->getRequest()->files();

        $elementId = $allData['participant_user_id'] ?? null;
        $force = $allData['force_save'] ?? false;
        $assignmentId = $allData['assignment_id'] ?? null;
        $skipActivityFeed = $allData['skip_activity_feed'] ?? false;

        $moduleName = $form->getModule()->getKey();
        $handler = null;
        $handlerClass = sprintf(
            'App\\Handler\\Modules\\%sHandler',
            preg_replace_callback('/(?:^|_)(.?)/', static function ($str) {
                return str_replace('_', '', strtoupper($str[0]));
            }, $moduleName)
        );

        if (class_exists($handlerClass)) {
            $handler = $this->container->get($handlerClass);

            $handler->setElementId($elementId);
            $handler->setForm($form);
            $handler->set($data);
            $handler->setForce($force);
            $handler->setContainer($this->container);
            $handler->setDataId($allData['data_id']);
            $handler->setAccount($this->account());

            $error = $handler->validate();

            if ($error !== null) {
                return $this->getResponse()->validation($error);
            }

            // Before action
            $handler->before($this->access(), $this->account());
            $output = $handler->output();
        }

        // Element ID
        if (isset($output['id'])) {
            $elementId = $output['id'];
            $handler->setElementId($elementId);
        }

        if ($assignmentId) {
            $assignment = $this->getDoctrine()->getRepository('App:Assignments')->findOneBy(['id' => $assignmentId]);
        } else {
            $assignment = null;
        }

        $formData = $this->getDoctrine()->getRepository('App:FormsData')->find($allData['data_id']);
        $formData->setAssignment($assignment);
        $formData->setElementId($elementId ?: 0);
        $formData->setEditor($this->user());

        $this->assignCaseManagers($elementId, $formData);

        $em = $this->getDoctrine()->getManager();
        $em->flush();

        $systemValues = null;

        if ($handler !== null) {
            $systemValues = $handler->systemValues();
        }

        if (is_iterable($systemValues) && count($systemValues)) {
            foreach ($systemValues as $formFieldName => $value) {
                $data[] = ['name' => $formFieldName, 'value' => $value];
            }
        }

        $saveValuesService->clearAllValues($formData);
        $saveValuesService->storeValues($formData, $data, $files);

        if ($handler !== null) {
            $handler->after($formData->getId());
            $this->output += $handler->output();
        }

        $this->output['data_id'] = $formData->getId();

        if (!$skipActivityFeed && in_array($moduleName, ['activities_services', 'assessment_outcomes'])) {
            $eventData = [
                'participant_id' => $elementId,
                'template'       => $moduleName,
                'title'          => $form->getName(),
                'template_id'    => $formData->getId(),
                'details'        => ['action' => 'updated']
            ];

            $eventDispatcher->dispatch(new FormsValuesCreatedEvent($eventData), FormsValuesCreatedEvent::class);
        }

        $eventDispatcher->dispatch(new FormUpdatedEvent($formData), FormUpdatedEvent::class);

        if(!isset($this->output['message'])) {
            $this->output['message'] = 'Form saved!';
        }

        return $this->getResponse()->success($this->output);
    }

    /**
     * @param $elementId
     * @param $referralId
     * @param \App\Entity\FormsData $formData
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     */
    private function referralEnrolled($elementId, $referralId, FormsData $formData, EventDispatcherInterface $eventDispatcher): void
    {
        $eventData = [
            'participant_id' => $elementId,
            'referral_id'    => $referralId,
            'form_data_id'   => $formData->getId(),
            'user'           => $this->user()
        ];
        $this->output['update_messages'] = true;
        $eventDispatcher->dispatch(new ReferralEnrolledEvent($eventData), ReferralEnrolledEvent::class);
    }

    /**
     * Case Manager who was assigned to the participant at the time of the submission
     *
     * @param $elementId
     * @param \App\Entity\FormsData $formData
     */
    private function assignCaseManagers($elementId, \App\Entity\FormsData $formData): void
    {
        if ($pData = $this->getDoctrine()->getRepository('App:UsersData')->findOneBy([
            'user' => $elementId
        ])) {
            $manager = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                'id' => (int)$pData->getCaseManager()
            ]);
            $manager2 = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                'id' => (int)$pData->getCaseManagerSecondary()
            ]);
        }
        $formData->setManager($manager ?? null);
        $formData->setSecondaryManager($manager2 ?? null);
        $em = $this->getDoctrine()->getManager();
        $em->persist($formData);
        $em->flush();
    }

}
