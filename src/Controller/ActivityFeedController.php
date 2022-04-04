<?php

namespace App\Controller;

use App\Exception\ExceptionMessage;
use App\Utils\Helper;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;

/**
 * Class ActivityFeedController
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
class ActivityFeedController extends Controller
{
    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @api {post} /activities Get all Activity Feeds
     * @apiGroup ActivityFeeds
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} from Date
     * @apiParam {String} to Date
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

        $minDate = (new \DateTime())->modify('-30 days')->setTime(0, 0, 0)->format('Y-m-d H:I:s');

        $params = $this->getRequest()->params();
        $from = isset($params['from']) && (bool)strtotime($params['from']) ? $params['from'] : $minDate;
        $to = isset($params['to']) && (bool)strtotime($params['to']) ? $params['to'] : null;
        $page = isset($params['page']) ? ((int)$params['page'] > 0 ? (int)$params['page'] : 1) : 1;

        $qb = $this->getDoctrine()->getRepository('App:ActivityFeed')
            ->getIndex($this->user(), $this->access(), $this->account(), ['from' => $from, 'to' => $to])->getQuery();

        $adapter = new DoctrineORMAdapter($qb);
        $pagerfanta = new Pagerfanta($adapter);

        $pagerfanta->setMaxPerPage(20);
        $pagerfanta->setCurrentPage($page);

        $data = [];
        foreach ($pagerfanta->getCurrentPageResults() as $row) {
            $data[] = [
                'template'    => $row->getTemplate(),
                'title'       => $row->getTitle(),
                'created'     => $row->getCreatedAt(),
                'participant' => [
                    'id'       => $row->getParticipant() ? $row->getParticipant()->getId() : null,
                    'fullName' => $row->getParticipant() ? $row->getParticipant()->getData()->getFullName(false) : null,
                    'avatar'   => $row->getParticipant() ? $row->getParticipant()->getData()->getAvatar() : null
                ],
                'details'     => $row->getDetails(),
                'template_id' => $row->getTemplateId(),
                'id'          => $row->getId()
            ];
        }

        return $this->getResponse()->success([
            'data'  => $data,
            'page'  => $pagerfanta->getCurrentPage(),
            'pages' => $pagerfanta->getNbPages(),
            'total' => $pagerfanta->getNbResults(),
            'from'  => $from,
            'to'    => $to
        ]);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse|Response
     * @api {get} /activities/export Export to CSV
     * @apiGroup ActivityFeeds
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} from Date
     * @apiParam {String} to Date
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

        if ($request->isMethod('GET')) {
            $from = (bool)strtotime($request->query->get('from')) ? $request->query->get('from') : null;
            $to = (bool)strtotime($request->query->get('to')) ? $request->query->get('to') : null;

            $results = $this->getDoctrine()->getRepository('App:ActivityFeed')
                ->getReport($this->user(), $this->access(), $this->account(), ['from' => $from, 'to' => $to])
                ->getQuery()
                ->getResult();

            $timezone = $this->user()->getData()->getTimeZone();
            $now = new \DateTime();
            $format = 'm/d/Y h:i A';

            if ($timezone) {
                try {
                    $now->setTimezone(new \DateTimeZone($timezone));
                    if ($timezone === 'Etc/GMT-12') {
                        $format = 'Y/m/d h:i A';
                    }
                } catch (\Exception $e) {
                    return $this->getResponse()->error(ExceptionMessage::INVALID_TIMEZONE, 401);
                }
            }

            $data[] = [$now->format($format)];
            $data[] = [];

            $data[] = [
                'Participant Name',
                'System ID',
                'Organization ID',
                'Case Manager',
                'Activity Message',
                'Date'
            ];

            foreach ($results as $row) {
                switch ($row['template']) {
                    case 'activities_services':
                        $msg = $row['first_name'] . ' ' . $row['last_name'] . ' completed Activity and Services - ' . $row['title'];
                        break;
                    case 'assessment_outcomes':
                        $msg = $row['first_name'] . ' ' . $row['last_name'] . ' completed Assessment and Outcomes - ' . $row['title'];
                        break;
                    case 'case_note':
                        $msg = 'Case Note completed for ' . $row['first_name'] . ' ' . $row['last_name'];
                        break;
                    case 'messages_outbound':
                        $msg = $row['title'] . ' sent a message to ' . $row['first_name'] . ' ' . $row['last_name'];
                        break;
                    case 'mass_messsages':
                        $msg = $row['title'] . ' sent a message to participants at ' . $row['created_at'];
                        break;
                    case 'messages_inbound':
                        $msg = $row['first_name'] . ' ' . $row['last_name'] . ' sent a message';
                        break;
                    case 'import':
                    case 'referral_enrolled':
                    case 'referral_not_enrolled':
                        $msg = $row['title'];
                        break;
                    default:
                        $msg = '';
                }

                $date = $row['createdAt'];
                $format = 'm/d/Y h:i A';

                if ($timezone) {
                    $date->setTimezone(new \DateTimeZone($timezone));
                    if ($timezone === 'Etc/GMT-12') {
                        $format = 'Y/m/d h:i A';
                    }
                }

                $data[] = [
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['system_id'],
                    $row['organization'],
                    $row['manager_first_name'] . ' ' . $row['manager_last_name'],
                    '"' . $msg . '"',
                    $date->format($format)
                ];
            }

            $file_name = 'ActivityFeeds';

            return new Response(
                (Helper::csvConvert($data)),
                200,
                [
                    'Content-Type'        => 'application/csv',
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $file_name . '.csv'),
                ]
            );
        }

        return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
    }
}
