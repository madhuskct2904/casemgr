<?php

namespace App\Controller;

use App\Entity\Users;
use App\Entity\UsersActivityLog;
use App\Exception\ExceptionMessage;
use App\Utils\Helper;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UsersActivityLogController extends Controller
{
    public function indexAction(Users $user, Request $request)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $params = $request->query->all();

        $from = isset($params['from']) && (bool)strtotime($params['from']) ? new \DateTime($params['from']) : null;
        $to = isset($params['to']) && (bool)strtotime($params['to']) ? new \DateTime($params['to']) : null;
        $page = isset($params['page']) ? ((int)$params['page'] > 0 ? (int)$params['page'] : 1) : 1;

        $entries = $this->getDoctrine()->getRepository('App:UsersActivityLog')->findForUser($user, $from, $to);

        $adapter = new DoctrineORMAdapter($entries);
        $pagerfanta = new Pagerfanta($adapter);

        $pagerfanta->setMaxPerPage(10);
        $pagerfanta->setCurrentPage($page);

        $log = [];

        foreach ($pagerfanta->getCurrentPageResults() as $entry) {
            $log[] = $this->formatEntryAsArray($entry);
        }

        return $this->getResponse()->success([
            'user_name' => $user->getData()->getFullName(),
            'logs'      => $log,
            'pages'         => $pagerfanta->getNbPages(),
            'total'         => $pagerfanta->getNbResults(),
            'from'          => $params['from'] ?? null,
            'to'            => $params['to'] ?? null
        ]);
    }

    public function exportAction(Request $request)
    {
        if (!$request->isMethod('GET')) {
            $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
        }

        $token = $request->query->get('token');

        if (!$session = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneBy(['token' => $token])) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 404);
        }

        $requestingUser = $session->getUser();

        if ($this->access($requestingUser) < Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $offset = Helper::getTimezoneOffset($requestingUser->getData()->getTimeZone());

        $params = $request->query->all();
        $userId = $params['id'];

        $from = null;
        $to = null;

        if (isset($params['from']) && (bool)strtotime($params['from'])) {
            $from = new \DateTime($params['from']);
        }

        if (isset($params['to']) && (bool)strtotime($params['to'])) {
            $to = new \DateTime($params['to']);
        }

        $user = $this->getDoctrine()->getRepository('App:Users')->find($userId);
        $logs = $this->getDoctrine()->getRepository('App:UsersActivityLog')->findForUser($user, $from, $to)->getResult();

        $tmp = fopen('php://temp', 'r+');

        $exportDate = new \DateTime();
        $exportDate->modify(sprintf("%+d", $offset * -1) . ' hours');

        $exportDate = 'User Activity Log: ' . $user->getData()->getFullName() . ', ' . $exportDate->format($this->phpDateFormat($user) . ' h:i A');

        fputcsv($tmp, [$exportDate]);
        fputcsv($tmp, ['']);
        fputcsv($tmp, ['Timestamp', 'Message', 'Account']);

        foreach ($logs as $log) {
            $date = $log->getDateTime()->modify(sprintf("%+d", $offset * -1) . ' hours')->format($this->phpDateFormat($user) . ' h:i A');

            $details = $log->getDetails();
            $account = isset($details['account_name']) ? $details['account_name'] : '';
            fputcsv($tmp, [$date, $log->getMessage(), $account]);
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
                'Content-Disposition' => 'attachment;filename="user-activity.csv"'
            ]
        );
    }

    private function formatEntryAsArray(UsersActivityLog $entry)
    {
        return [
            'title'        => $entry->getEventName(),
            'date'         => $entry->getDateTime(),
            'message'      => $entry->getMessage(),
            'details'      => $entry->getDetails(),
            'account_name' => isset($entry->getDetails()['account_name']) ? $entry->getDetails()['account_name'] : ''
        ];
    }
}
