<?php

namespace App\Controller;

use App\Entity\FormsData;
use App\Entity\FormsValues;
use App\Event\FormsValuesCreatedEvent;
use App\Event\FormCreatedEvent;
use App\Exception\ExceptionMessage;
use App\Service\Forms\SaveValuesService;
use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class GroupsController
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
class GroupsController extends Controller
{
    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @api {post} /groups Get Group Forms
     * @apiGroup Groups
     *
     * @apiHeader {String} token Authorization Token
     *
     * @apiSuccess {Array} data Results
     *
     * @apiError message Error Message
     *
     */
    public function indexAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $data = [];
        $module = $this->getDoctrine()->getRepository('App:Modules')->findOneBy(['key' => 'activities_services']);

        if (!$module) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_MODULE);
        }

        $forms = $this->getDoctrine()->getRepository('App:Forms')->findByModuleAccountsAccessLevel($module, [$this->account()], $this->access(), true);

        foreach ($forms as $form) {
            $data[] = [
                'id'   => $form->getId(),
                'name' => $form->getName()
            ];
        }

        return $this->getResponse()->success($data);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @api {post} /groups/create Save Group Forms
     * @apiGroup Groups
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Array} data Form Values
     * @apiParam {Array} elements Users Ids
     * @apiParam {Integer} form_id Form Id
     *
     * @apiSuccess {String} message Success Message
     *
     * @apiError message Error Message
     *
     */
    public function createAction(SaveValuesService $saveValuesService, EventDispatcherInterface $eventDispatcher)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $data = $this->getRequest()->param('data');
        $elements = $this->getRequest()->param('elements');
        $formId = $this->getRequest()->param('form_id');

        if (!$module = $this->getDoctrine()->getRepository('App:Modules')->findOneBy(['key' => 'activities_services'])) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_MODULE);
        }

        $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($formId, $this->account(), $this->access());

        if (!$form) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM_ID);
        }

        if (!is_array($elements)) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANTS);
        }

        $participantType = $this->account()->getParticipantType();
        $participants = $this->getDoctrine()->getRepository('App:Users')->findParticipantsForGroupsByIds($elements, $participantType);

        foreach ($participants as $participant) {
            $formsData = $saveValuesService->createFormData($form, $this->account(), $this->user(), $participant);

            $saveValuesService->storeValues($formsData, $data, [], $this->user(), $this->account());

            $eventData = [
                'participant_id' => $participant->getId(),
                'template'       => 'activities_services',
                'title'          => $form->getName(),
                'template_id'    => $formsData->getId()
            ];

            $eventDispatcher->dispatch(new FormsValuesCreatedEvent($eventData), FormsValuesCreatedEvent::class);
            $eventDispatcher->dispatch(new FormCreatedEvent($formsData), FormCreatedEvent::class);
        }

        return $this->getResponse()->success(['Forms saved.']);
    }
}
