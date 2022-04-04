<?php

namespace App\Controller;

use App\Entity\Events;
use App\Entity\Users;
use App\Event\ActivityFeedEvent;
use App\Exception\ExceptionMessage;
use DateTimeZone;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Request;
use App\Utils\Helper;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;

/**
 * Class EventsController
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
class EventsController extends Controller
{
    const EVENT_BACKGROUND_COLOUR = '#d09f23';

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository
     */
    private $repository;

    /**
     * @var string
     */
    private $format;

    /**
     * @var string
     */
    private $formatCalendar;

    /**
     * @var null|string
     */
    private $timezone;

    /**
     * EventsController constructor.
     */
    public function __construct()
    {
        $this->format     = 'm/d/Y h:i A';
        $this->formatCalendar = 'Y-m-d H:i';
    }

    /**
     * @param Events $row
     * @return array
     */
    protected function transform(Events $row, $strip = false)
    {
        return [
            'id'              => $row->getId(),
            'title'           => $strip ? strip_tags($row->getTitle()) : $row->getTitle(),
            'participant_id'  => is_object($row->getParticipant()) ? $row->getParticipant()->getId() : null,
            'all_day'         => $row->isAllDay(),
            'start_date_time' => $row->getStartDateTime()->format($this->format),
            'end_date_time'   => $row->getEndDateTime()->format($this->format),
            'start'           => $row->getStartDateTime()->format($this->formatCalendar),
            'end'             => $row->getEndDateTime()->format($this->formatCalendar),
            'comment'         => $row->getComment(),
            'color'           => self::EVENT_BACKGROUND_COLOUR,
        ];
    }

    /**
     * @param $values
     * @return array|null
     */
    protected function validate($values): ?array
    {
        $errors = [];

        if (!isset($values['title']) || !$values['title']) {
            $errors['Title'][] = 'Title field is required.';
        } elseif (!isset($values['title']) || strlen($values['title']) > 4096) {
            $errors['Title'][] = 'Title field is too long.';
        }

        return count($errors) ? $errors : null;
    }

    /**
     * @api {post} /events/index Get all Events
     * @apiGroup Events
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} start_date Date
     * @apiParam {String} end_date Date
     *
     * @apiSuccess {Array} data Results
     *
     * @apiError message Error Message
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function indexAction()
    {
        $data = [];

        $this->repository  = $this->getDoctrine()->getRepository('App:Events');

        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $from  = $this->getRequest()->param('start_date');
        $to    = $this->getRequest()->param('end_date');

        if ($from and $to) {
            $events = $this->repository->getAllByDates($from, $to, $this->user(), $this->account())->getResult();
        } else {
            $events = $this->repository->getAll($this->user(), $this->account())->getResult();
        }

        foreach ($events as $event) {
            $data[] = $this->transform($event, true);
        }

        return $this->getResponse()->success($data);
    }

    /**
     * @api {post} /events/index-by-dates Get Paginated Events
     * @apiGroup Events
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} page Page number
     * @apiParam {String} start_date Date
     * @apiParam {String} end_date Date
     *
     * @apiSuccess {Array} data Results
     * @apiSuccess {Integer} pages Number of pages
     *
     * @apiError message Error Message
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function indexByDatesAction(Request $request)
    {
        $data = [];

        $this->repository  = $this->getDoctrine()->getRepository('App:Events');

        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $page  = $this->getRequest()->param('page') ?: 1;
        $from  = $this->getRequest()->param('start_date');
        $to    = $this->getRequest()->param('end_date');
        $events = $this->repository->getAllByDates($from, $to, $this->user(), $this->account());



        $adapter    = new DoctrineORMAdapter($events);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(10);
        $pagerfanta->setCurrentPage($page);

        foreach ($pagerfanta->getCurrentPageResults() as $event) {
            $data[] = $this->transform($event, true);
        }

        return $this->getResponse()->success(['data' => $data, 'pages' => $pagerfanta->getNbPages()]);
    }

    /**
     * @api {post} /events/show Get Event
     * @apiGroup Events
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} event_id Event Id
     *
     * @apiSuccess {Array} event Result
     *
     * @apiError message Error Message
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function showAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $this->repository  = $this->getDoctrine()->getRepository('App:Events');

        $event_id = $this->getRequest()->param('event_id');

        if ($event_id !== null) {
            if (!$event = $this->repository->findOneBy(['id' => $event_id])) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_EVENT_ID, 404);
            }

            if (!$this->can(Users::ACCESS_LEVELS['VOLUNTEER'], null, $event->getAccount())) {
                return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
            }

            return $this->getResponse()->success(
                $this->transform($event)
            );
        }

        return $this->getResponse()->error(ExceptionMessage::MISSING_EVENT_ID);
    }

    /**
     * @api {post} /events/save Create/Update Event
     * @apiGroup Events
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} [id] Event Id
     *
     * @apiSuccess {String} message Success Message
     * @apiSuccess {Integer} id Event Id
     *
     * @apiError message Error Message
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function saveAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($errors = $this->validate($this->getRequest()->params())) {
            return $this->getResponse()->validation($errors);
        }

        $this->repository  = $this->getDoctrine()->getRepository('App:Events');

        $event_id = $this->getRequest()->param('id');

        if ($event_id !== null) {
            $event = $this->repository->find($event_id);

            if (!$this->can(Users::ACCESS_LEVELS['VOLUNTEER'], null, $event->getAccount())) {
                return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
            }

            $this->repository->save($event, $this->user(), $this->account(), $this->getRequest());
            return $this->getResponse()->success(['Event Edited']);
        } else {
            $event = $this->repository->save(null, $this->user(), $this->account(), $this->getRequest());

            return $this->getResponse()->success(['Event Added', 'id' => $event->getId()]);
        }
    }

    /**
     * @api {post} /events/delete Delete Event
     * @apiGroup Events
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} event_id Event Id
     *
     * @apiSuccess {String} message Success Message
     *
     * @apiError message Error Message
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function deleteAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $this->repository  = $this->getDoctrine()->getRepository('App:Events');

        $id 	= $this->getRequest()->param('event_id');

        if ($id === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_EVENT_ID);
        }

        $event 	= $this->repository->find($id);

        if ($event === null) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_EVENT);
        }

        if (!$this->can(Users::ACCESS_LEVELS['VOLUNTEER'], null, $event->getAccount())) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $this->repository->remove($event);

        return $this->getResponse()->success(['Event removed']);
    }

    /**
     * @api {get} /events/export Export to CSV
     * @apiGroup Events
     *
     * @apiHeader {String} token Authorization Token
     *
     * @apiParam {String} start_date Date
     * @apiParam {String} end_date Date
     *
     * @apiSuccess {File} Response CSV File
     *
     * @apiError message Error message
     *
     * @param Request $request
     * @return Response|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function exportAction(Request $request)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $this->repository  = $this->getDoctrine()->getRepository('App:Events');


        $from  = $this->getRequest()->param('start_date');
        $to    = $this->getRequest()->param('end_date');
        $events = $this->repository->getAllByDates($from, $to, $this->user(), $this->account())->getResult();

        $now        = new \DateTime();

        $this->timezone   = $this->user()->getData()->getTimeZone();
        if ($this->timezone and $this->timezone === 'Etc/GMT-12') {
            $this->format = 'Y/m/d h:i A';
        }

        if ($this->timezone) {
            try {
                $now->setTimezone(new DateTimeZone($this->timezone));
            } catch (\Exception $e) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_TIMEZONE, 401);
            }
        }

        $firstRow = $now->format($this->format);

        $data[] = [
                '"' . $firstRow . '"'
            ];

        $data[]       = [
                'Title',
                'System ID',
                'Organization ID',
                'Start Date and Time',
                'End Date and Time',
                'Comments'
            ];

        foreach ($events as $row) {
            $participant = $row->getParticipant();

            $data[] = [
                    '"' . str_replace('&nbsp;', ' ', strip_tags($row->getTitle())) . '"',
                    is_object($participant) ? $participant->getData()->getSystemId() : null,
                    is_object($participant) ? $participant->getData()->getOrganizationId() : null,
                    $row->getStartDateTime()->format($this->format),
                    $row->getEndDateTime()->format($this->format),
                    '"' . $row->getComment() . '"',
                ];
        }

        $file_name  = 'Events';

        return new Response(
            (Helper::csvConvert($data)),
            200,
            [
                    'Content-Type'        => 'application/csv',
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $file_name . '.csv'),
                ]
        );
    }
}
