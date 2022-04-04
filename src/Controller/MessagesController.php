<?php

namespace App\Controller;

use App\Entity\Messages;
use App\Entity\Users;
use App\Enum\MessageStrings;
use App\Enum\ParticipantStatus;
use App\Event\MessagesCreatedEvent;
use App\Exception\ExceptionMessage;
use App\Service\MessageService;
use App\Utils\Helper;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\TwiML\MessagingResponse;
use function Sentry\captureException;

/**
 * Class MessagesController
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
class MessagesController extends Controller
{

    /**
     * @return JsonResponse
     */
    public function getAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $participantId = $this->getRequest()->param('participant_id');
        $search        = $this->getRequest()->param('search');

        $participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
            'id'   => $participantId,
            'type' => 'participant'
        ]);

        if (! $participant) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT);
        }

        $user       = $this->user();
        $userData   = $user->getData();
        $repository = $this->getDoctrine()->getRepository('App:Messages');
        $timezone   = null !== $userData ? $userData->getTimeZone() : null;

        if (null !== $timezone) {
            $repository->setTimezone(new DateTimeZone($timezone));
        }

        try {
            $messages = $repository->getByParticipant($participant, $user, $search);
        } catch (Exception $exception) {
            $messages = [];

            captureException($exception); // report to Sentry
        }

        $pPhone = preg_replace('/[^0-9]/', '', $participant->getData()->getPhoneNumber());

        return $this->getResponse()->success([
            'messages'     => $messages,
            'replyPhone'   => $pPhone ? '+' . $pPhone : null,
            'twilioStatus' => $this->account()->isTwilioStatus()
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function sendAction(MessageService $messageService, EventDispatcherInterface $eventDispatcher)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $body = $this->getRequest()->param('body', null);

        if (! is_string($body) || strlen($body) < 1 || strlen($body) > 255) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_MESSAGE_LENGTH);
        }

        $participantId = $this->getRequest()->param('participant_id');

        $participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
            'id'   => $participantId,
            'type' => 'participant'
        ]);

        if (! $participant) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT, 404);
        }

        if (! $pAccount = $participant->getAccounts()->first()) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT_ACCOUNT, 404);
        }

        if (! $this->can(Users::ACCESS_LEVELS['VOLUNTEER'], null, $pAccount)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        if (! $pAccount->isTwilioStatus()) {
            return $this->getResponse()->error(ExceptionMessage::DISABLED_MESSAGING, 400);
        }

        try {
            if (! $accountPhone = $this->account()->getTwilioPhone()) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_PHONE_NUMBER, 400);
            }

            $fromNumber = Helper::convertPhone($accountPhone);
            $toNumber   = Helper::convertPhone($participant->getData()->getPhoneNumber());

            if (! $fromNumber || ! $toNumber) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_PHONE_NUMBER);
            }

            try {
                $messages = $messageService->sendMessage(
                    $body,
                    $toNumber,
                    $fromNumber
                );

                $status    = $messages['status'];
                $sid       = $messages['sid'];
                $error     = null;
            } catch (Exception $exception) {
                $error = $exception->getMessage();

                $status    = MessageStrings::ERROR_MESSAGE_STATUS;
                $sid       = null;
            }

            if ($body && $status) {
                $message = new Messages();

                $message->setBody($body);
                $message->setUser($this->user());
                $message->setFromPhone($fromNumber);
                $message->setParticipant($participant);
                $message->setToPhone($toNumber);
                $message->setStatus($status);
                $message->setType(MessageStrings::OUTBOUND);
                $message->setCreatedAt(new DateTime());
                $message->setSid($sid);
                $message->setError($error);

                $em = $this->getDoctrine()->getManager();
                $em->persist($message);
                $em->flush();

                if ($error) {
                    $errorMessage = new Messages();

                    $status = MessageStrings::ERROR_RESPONSE_MESSAGE_STATUS;
                    $sid    = null;
                    $body   = MessageStrings::ERROR_MESSAGE;

                    $errorMessage->setBody($body);
                    $errorMessage->setUser($this->user());
                    $errorMessage->setFromPhone($fromNumber);
                    $errorMessage->setParticipant($participant);
                    $errorMessage->setToPhone($toNumber);
                    $errorMessage->setStatus($status);
                    $errorMessage->setType(MessageStrings::INBOUND);
                    $errorMessage->setCreatedAt(new DateTime());
                    $errorMessage->setSid($sid);
                    $errorMessage->setError($error);

                    $em->persist($errorMessage);
                    $em->flush();
                }

                if (is_null($error)) {
                    $eventDispatcher->dispatch(
                        new MessagesCreatedEvent(['message' => $message]),
                        MessagesCreatedEvent::class
                    );

                    return $this->getResponse()->success([
                        'message' => [
                            'participant' => [
                                'id'       => $message->getParticipant()->getData()->getId(),
                                'fullName' => $message->getParticipant()->getData()->getFullName(false),
                                'avatar'   => $message->getParticipant()->getData()->getAvatar()
                            ],
                            'user'        => [
                                'id'       => $message->getUser()->getData()->getId(),
                                'fullName' => $message->getUser()->getData()->getFullName(false),
                                'avatar'   => $message->getUser()->getData()->getAvatar()
                            ],
                            'createdAt'   => $message->getCreatedAt(),
                            'body'        => $message->getBody(),
                            'type'        => $message->getType()
                        ]
                    ]);
                }

                return $this->getResponse()->error($error);
            } else {
                return $this->getResponse()->error(ExceptionMessage::WRONG_TWILIO_RESPONSE);
            }
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }
    }

    /**
     * Twilio web hook
     *
     * @param Request $request
     *
     * @return Response
     * @throws NonUniqueResultException
     */
    public function receiveAction(Request $request, EventDispatcherInterface $eventDispatcher)
    {
        $data = $request->request->all();

        $body      = isset($data['Body']) ? $data['Body'] : null;
        $fromPhone = Helper::convertPhone(isset($data['From']) ? $data['From'] : null);
        $toPhone   = Helper::convertPhone(isset($data['To']) ? $data['To'] : null);
        $status    = isset($data['SmsStatus']) ? $data['SmsStatus'] : null;
        $sid       = isset($data['MessageSid']) ? $data['MessageSid'] : null;

        if ($body && $fromPhone && $toPhone && $status && $sid) {
            if ($participant = $this->getDoctrine()->getRepository('App:Messages')
                                    ->getParticipantByPhone($fromPhone, $toPhone)) {
                $pAccount = $participant->getAccounts()->first();

                if ($participant->getData()->getStatus() === ParticipantStatus::DISMISSED) {
                    if ($pAccount) {
                        // Reply msg
                        return $this->twilioResponse(sprintf(
                            'This is an automated message. Please contact %s for assistance.',
                            $pAccount->getOrganizationName()
                        ));
                    }

                    // Participant is dismissed
                    return $this->twilioResponse(null);
                } else {
                    if (! $pAccount->isTwilioStatus()) {
                        // Messaging is disabled for this account
                        return $this->twilioResponse(null);
                    }

                    $manager = null;
                    $secondaryManager = null;

                    if ($managerId = $participant->getData()->getCaseManager()) {
                        $manager = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                            'id'   => $managerId,
                            'type' => 'user'
                        ]);
                    }

                    if ($managerId = $participant->getData()->getCaseManagerSecondary()) {
                        $secondaryManager = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                            'id'   => $managerId,
                            'type' => 'user'
                        ]);
                    }

                    $message = new Messages();

                    $message->setBody(substr($body, 0, 255));
                    $message->setParticipant($participant);
                    $message->setFromPhone($fromPhone);
                    $message->setUser($manager); // for alerts only
                    $message->setCaseManagerSecondary($secondaryManager);
                    $message->setToPhone($toPhone);
                    $message->setStatus($status);
                    $message->setType('inbound');
                    $message->setCreatedAt(new DateTime());
                    $message->setSid($sid);

                    $em = $this->getDoctrine()->getManager();
                    $em->persist($message);
                    $em->flush();

                    $eventDispatcher->dispatch(
                        new MessagesCreatedEvent(['message' => $message]),
                        MessagesCreatedEvent::class
                    );
                }
            } else {
                // Participant not found
                return $this->twilioResponse(null);
            }
        } else {
            // Bad request
            return $this->twilioResponse(null);
        }

        // Saved
        return $this->twilioResponse(null);
    }

    /**
     * @param string|null $message
     *
     * @return Response
     */
    private function twilioResponse(?string $message = null): Response
    {
        $response = new MessagingResponse();

        if ($message !== null) {
            $response->message($message);
        }

        return new Response(
            $response,
            200,
            [
                'Content-Type' => 'text/xml'
            ]
        );
    }

    public function exportAction(Request $request)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($request->isMethod('GET')) {
            $id     = $request->query->get('id');
            $search = $request->query->get('search');

            if (! $participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                'id'   => $id,
                'type' => 'participant'
            ])) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT, 401);
            }

            if (! $this->can(Users::ACCESS_LEVELS['VOLUNTEER'], null, $participant->getAccounts()->first())) {
                return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
            }

            $user       = $this->user();
            $userData   = $user->getData();
            $repository = $this->getDoctrine()->getRepository('App:Messages');
            $timezone   = null !== $userData ? $userData->getTimeZone() : null;

            if (null !== $timezone) {
                $repository->setTimezone(new DateTimeZone($timezone));
            }

            $messages = $repository->getByParticipant($participant, $user, $search);

            $data[] = [
                'Date of Export ' . $this->convertDateTime($user)
            ];

            $data[] = [
                '"' . $participant->getData()->getFullName() . '"'
            ];

            $data[] = [];

            $data[] = [
                'Sender',
                'Timestamp',
                'Message'
            ];

            foreach ($messages as $row) {
                if ($row['type'] === 'outbound') {
                    $sender = isset($row['user']) ? $row['user']['fullName'] : '';
                } else {
                    $sender = isset($row['participant']) ? $row['participant']['fullName'] : '';
                }

                $data[] = [
                    '"' . $sender . '"',
                    $this->convertDateTime($this->user(), $row['createdAt']),
                    '"' . $row['body'] . '"'
                ];
            }

            return $this->exportCsv($data);
        }

        return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse|Response
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportHistoryAction(Request $request)
    {
        if ($request->isMethod('GET')) {
            $token = $request->query->get('token');

            if (! $session = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneBy(['token' => $token])) {
                return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 404);
            }

            $pid  = $request->query->get('pid');
            $aid  = $request->query->get('aid', false);
            $type = in_array(
                $request->query->get('type'),
                ['csv', 'xls', 'pdf']
            ) ? $request->query->get('type') : 'csv';

            if (! $participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                'id' => $pid
            ])) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT, 404);
            }

            if (! $this->can(
                Users::ACCESS_LEVELS['VOLUNTEER'],
                $session->getUser(),
                $participant->getAccounts()->first()
            )) {
                return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
            }

            if ($aid !== false) {
                if (! $assignment = $this->getDoctrine()->getRepository('App:Assignments')->findOneBy([
                    'id'          => $aid,
                    'participant' => $pid
                ])) {
                    return $this->getResponse()->error(ExceptionMessage::INVALID_ASSIGNMENT, 401);
                }
            } else {
                $assignment = null;
            }

            $messages = $this->getDoctrine()->getRepository('App:Messages')->findBy([
                'assignment'  => $assignment ? $assignment->getId() : null,
                'participant' => $participant->getId()
            ]);

            $values = [];
            $fields = [];

            if ($fd = $this->getDoctrine()->getRepository('App:FormsData')->findOneBy([
                'module'     => 1,
                'element_id' => $participant->getId(),
                'assignment' => $assignment ? $assignment->getId() : null
            ])) {
                $map    = json_decode($fd->getForm()->getColumnsMap(), true);
                $fields = (function () use ($map) {
                    $fields = [];
                    foreach ($map as $map_row) {
                        $fields[$map_row['name']] = $map_row['value'];
                    }

                    return $fields;
                })();

                foreach ($fd->getValues() as $value) {
                    $values[$value->getName()] = $value->getValue();
                }
            }

            $firstName = (isset($fields['first_name']) && isset($values[$fields['first_name']])) ? $values[$fields['first_name']] : '';
            $lastName  = (isset($fields['last_name']) && isset($values[$fields['last_name']])) ? $values[$fields['last_name']] : '';

            $data[] = [
                'Date of Export ' . $this->convertDateTime($session->getUser())
            ];

            $data[] = [
                $type === 'csv' ? '"' . sprintf('%s, %s', $lastName, $firstName) . '"' : sprintf(
                    '%s, %s',
                    $lastName,
                    $firstName
                )
            ];

            $data[] = [];

            $data[] = [
                'Sender',
                'Timestamp',
                'Message'
            ];

            foreach ($messages as $row) {
                if ($row->getType() === 'outbound') {
                    $sender = $row->getUser() ? $row->getUser()->getData()->getFullName() : '';
                } else {
                    $sender = $row->getParticipant() ? $row->getParticipant()->getData()->getFullName() : '';
                }

                $data[] = [
                    $type === 'csv' ? '"' . $sender . '"' : $sender,
                    $this->convertDateTime($session->getUser(), $row->getCreatedAt()),
                    $type === 'csv' ? '"' . $row->getBody() . '"' : $row->getBody()
                ];
            }

            switch ($type) {
                case 'xls':
                    return $this->exportXls($data);
                    break;
                case 'pdf':
                    return $this->exportPdf($data);
                    break;
                default:
                    return $this->exportCsv($data);
                    break;
            }
        }

        return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
    }

    /**
     * @param $data
     *
     * @return Response
     */
    private function exportCsv($data)
    {
        $file_name = 'SmsMessaging';

        return new Response(
            (Helper::csvConvert($data)),
            200,
            [
                'Content-Type'        => 'application/csv',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $file_name . '.csv'),
            ]
        );
    }

    /**
     * @param $data
     *
     * @return Response
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    private function exportXls($data)
    {
        $spreadsheet = new Spreadsheet();
        $writer      = new Xlsx($spreadsheet);
        $sheet       = $spreadsheet->getActiveSheet();
        $file_name   = 'SmsMessaging';

        foreach ($data as $kR => $vR) {
            foreach ($vR as $kC => $vC) {
                $sheet->setCellValueByColumnAndRow($kC + 1, $kR + 1, $vC);
            }
        }

        ob_start();

        $writer->save('php://output');

        return new Response(
            ob_get_clean(),
            200,
            [
                'Content-Type'        => 'application/vnd.ms-excel; charset=utf-8',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $file_name . '.xlsx')
            ]
        );
    }

    /**
     * @param $data
     *
     * @return Response
     */
    private function exportPdf($data)
    {
        $firstRow  = $data[0];
        $secondRow = $data[1];
        $columns   = $data[3];

        unset($data[0]);
        unset($data[1]);
        unset($data[2]);
        unset($data[3]);

        $html = $this->renderView('Messages/pdf.html.twig', [
            'first_row'  => $firstRow[0],
            'second_row' => $secondRow[0],
            'columns'    => $columns,
            'messages'   => $data
        ]);

        $file_name = 'SmsMessaging';

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $file_name . '.pdf')
            ]
        );
    }
}
