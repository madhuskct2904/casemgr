<?php

namespace App\Controller;

use App\Entity\CaseNotes;
use App\Entity\Users;
use App\Enum\ParticipantStatus;
use App\Event\CaseNotesCreatedEvent;
use App\Exception\ExceptionMessage;
use App\Utils\Helper;
use DateTime;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use App\Form\CaseNotesType;

/**
 * Class CommunicationNotesController
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
class CommunicationNotesController extends Controller
{
    /**
     * @return JsonResponse
     * @api {post} /notes Get Participant Notes
     * @apiGroup CaseNotes
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} participant User Id
     * @apiParam {Integer} page
     * @apiParam {Integer} limit
     *
     * @apiSuccess {Array} data Results
     *
     * @apiError message Error Message
     *
     */
    public function indexAction(): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $participantId = $this->getRequest()->param('participant');
        $page = $this->getRequest()->param('page', 1);
        $limit = $this->getRequest()->param('limit', 10);

        $participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
            'id'   => $participantId,
            'type' => 'participant'
        ]);

        if (!$this->can(Users::ACCESS_LEVELS['VOLUNTEER'], null, $participant->getAccounts()->first())) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $search = $this->getRequest()->param('search', '');

        $qb = $this->getDoctrine()->getRepository('App:CaseNotes')->findByParticipantIdAndCurrentAssignment($participantId, $search);

        $adapter = new DoctrineORMAdapter($qb);
        $pagerfanta = new Pagerfanta($adapter);

        $pagerfanta->setMaxPerPage($limit);
        $pagerfanta->setCurrentPage($page);

        $data = [];

        foreach ($pagerfanta->getCurrentPageResults() as $row) {
            $data[] = [
                'type'      => $row->getType(),
                'note'      => $row->getNote(),
                'createdAt' => $row->getCreatedAt(),
                'updatedAt' => $row->getModifiedAt(),
                'createdBy' => $row->getCreatedBy()
                    ? ['fullName' => $row->getCreatedBy()->getData()->getFullName(false)]
                    : ['fullName' => 'System Administrator'],
                'updatedBy' => $row->getModifiedBy() ? [
                    'fullName' => $row->getModifiedBy()->getData()->getFullName(false)
                ] : null,
                'manager'   => $row->getManager() ? [
                    'fullName' => $row->getManager()->getData()->getFullName(false)
                ] : null,
                'id'        => $row->getId()
            ];
        }

        return $this->getResponse()->success([
            'page'  => $page,
            'pages' => $pagerfanta->getNbPages(),
            'data'  => $data
        ]);
    }

    public function indexForAssignmentAction(): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $participantId = $this->getRequest()->param('participant_id');
        $assignmentId = $this->getRequest()->param('assignment_id');

        if (!$participantId || !$assignmentId) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_DATA, 422);
        }

        $participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
            'id'   => $participantId,
            'type' => 'participant'
        ]);

        $assignment = $this->getDoctrine()->getRepository('App:Assignments')->find($assignmentId);

        if (!$this->can(Users::ACCESS_LEVELS['CASE_MANAGER'], null, $participant->getAccounts()->first())) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $rows = $this->getDoctrine()->getRepository('App:CaseNotes')->findBy([
            'participant' => $participant,
            'assignment'  => $assignment
        ]);

        $data = [];

        foreach ($rows as $row) {
            $data[] = [
                'type'      => $row->getType(),
                'note'      => $row->getNote(),
                'createdAt' => $row->getCreatedAt(),
                'updatedAt' => $row->getModifiedAt(),
                'createdBy' => $row->getCreatedBy()
                    ? ['fullName' => $row->getCreatedBy()->getData()->getFullName(false)]
                    : ['fullName' => 'System Administrator'],
                'updatedBy' => $row->getModifiedBy() ? [
                    'fullName' => $row->getModifiedBy()->getData()->getFullName(false)
                ] : null,
                'manager'   => $row->getManager() ? [
                    'fullName' => $row->getManager()->getData()->getFullName(false)
                ] : null,
                'id'        => $row->getId()
            ];
        }

        return $this->getResponse()->success(['index' => $data]);
    }

    /**
     * @return JsonResponse
     * @api {post} /notes/create Create Participant Note
     * @apiGroup CaseNotes
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} participant User Id
     * @apiParam {String} type Note type
     * @apiParam {String} note Note description
     *
     * @apiSuccess {String} message Success Message
     * @apiSuccess {Integer} id Case Note Id
     *
     * @apiError message Error Message
     * @apiError errors Form Errors
     *
     */
    public function createAction(EventDispatcherInterface $eventDispatcher): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $caseNote = new CaseNotes();
        $form = $this->createForm(CaseNotesType::class, $caseNote);
        $params = $this->getRequest()->params();

        $form->submit($params);

        if ($form->isValid()) {
            if (!$this->can(Users::ACCESS_LEVELS['CASE_MANAGER'], null, $caseNote->getParticipant()->getAccounts()->first())) {
                return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
            }

            if ($caseNote->getParticipant()->getData()->getStatus() === ParticipantStatus::DISMISSED) {
                return $this->getResponse()->error(ExceptionMessage::DISMISSED_PARTICIPANT, 400);
            }

            $manager = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                'id' => $caseNote->getParticipant()->getData()->getCaseManager()
            ]);

            $caseNote
                ->setCreatedBy($this->user())
                ->setManager($manager);

            $em = $this->getDoctrine()->getManager();
            $em->persist($caseNote);
            $em->flush();

            $noteData = [
                'participant' => $caseNote->getParticipant(),
                'template'    => 'case_note',
                'template_id' => $caseNote->getId()
            ];
            $eventDispatcher->dispatch(new CaseNotesCreatedEvent($noteData), CaseNotesCreatedEvent::class);

            return $this->getResponse()->success([
                'message' => 'Communication Note created.',
                'id'      => $caseNote->getId()
            ]);
        }

        return $this->getResponse()->validation([
            'errors' => Helper::getFormErrors($form)
        ]);
    }

    /**
     * @param $id
     * @return JsonResponse
     * @api {post} /notes/edit/:id Edit Participant Note
     * @apiGroup CaseNotes
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} :id User Id
     * @apiParam {String} type Note type
     * @apiParam {String} note Note description
     *
     * @apiSuccess {String} message Success Message
     * @apiSuccess {Integer} id Case Note Id
     *
     * @apiError message Error Message
     * @apiError errors Form Errors
     *
     */
    public function editAction($id): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$caseNote = $this->getDoctrine()->getRepository('App:CaseNotes')->findOneBy(['id' => $id])) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_CASE_NOTE);
        }

        if ($caseNote->isReadOnly()) {
            return $this->getResponse()->error(ExceptionMessage::READ_ONLY_CASE_NOTE);
        }

        $form = $this->createForm(CaseNotesType::class, $caseNote);
        $params = $this->getRequest()->params();

        $form->submit($params);

        if ($form->isValid()) {
            if (!$this->can(Users::ACCESS_LEVELS['CASE_MANAGER'], null, $caseNote->getParticipant()->getAccounts()->first())) {
                return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
            }

            $manager = $this->getDoctrine()->getRepository('App:Users')->findOneBy(
                ['id' => $caseNote->getParticipant()->getData()->getCaseManager()]
            );

            $caseNote
                ->setModifiedBy($this->user())
                ->setModifiedAt(new DateTime())
                ->setManager($manager);

            $em = $this->getDoctrine()->getManager();
            $em->persist($caseNote);
            $em->flush();

            return $this->getResponse()->success([
                'message' => 'Communication Note updated.',
                'id'      => $caseNote->getId()
            ]);
        }

        return $this->getResponse()->validation([
            'errors' => Helper::getFormErrors($form)
        ]);
    }

    private function typesLabels(): array
    {
        return [
            'collateral' => 'Collateral Contact',
            'email'      => 'Email',
            'person'     => 'In-Person',
            'phone'      => 'Phone',
            'social'     => 'Social/Messenger',
            'text'       => 'Text',
            'virtual'    => 'Virtual'
        ];
    }

    /**
     * @param Request $request
     * @return JsonResponse|Response
     * @api {post} /notes/export/ Export to CSV
     * @apiGroup CaseNotes
     *
     * @apiHeader {String} token Authorization Token
     *
     * @apiParam {Integer} participant_id User Id
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

        if (!$request->isMethod('GET')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
        }

        $id = $request->query->get('participant_id');

        $caseNotes = $this->getDoctrine()->getRepository('App:CaseNotes')->findBy([
            'assignment'  => null,
            'participant' => $id
        ]);

        if (!$participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
            'id' => $id
        ])) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT, 404);
        }

        if (!$this->can(Users::ACCESS_LEVELS['VOLUNTEER'], null, $participant->getAccounts()->first())) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $firstRow = $participant->getData()->getFullName(false) . ', ' . $participant->getData()->getSystemId() . ', ' . $participant->getData()->getOrganizationId() . ', ' . $this->convertDateTime($this->user());

        $data[] = [
            '"' . $firstRow . '"'
        ];

        $data[] = [];

        $types = $this->typesLabels();

        $data[] = [
            'Communication Type',
            'Communication Notes',
            'Timestamp',
            'Username',
        ];

        foreach ($caseNotes as $row) {
            $date = $row->getModifiedAt() ?: $row->getCreatedAt();

            $data[] = [
                $types[$row->getType()] ?? '',
                '"' . strip_tags($row->getNote()) . '"',
                $this->convertDateTime($this->user(), $date),
                $row->getModifiedBy() ? $row->getModifiedBy()->getData()->getFullName(false) : $row->getCreatedBy() ? $row->getCreatedBy()->getData()->getFullName(false) : 'System Administrator'
            ];
        }

        $fileName = 'CommunicationNotes';

        return new Response(
            (Helper::csvConvert($data)),
            200,
            [
                'Content-Type'        => 'application/csv',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName . '.csv'),
            ]
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse|Response
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    public function exportHistoryAction(Request $request)
    {
        if (!$request->isMethod('GET')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
        }

        $token = $request->query->get('token');

        if (!$session = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneBy(['token' => $token])) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $pid = $request->query->get('pid');
        $aid = $request->query->get('aid', false);
        $type = in_array($request->query->get('type'), ['csv', 'xls', 'pdf']) ? $request->query->get('type') : 'csv';

        if (!$participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
            'id' => $pid
        ])) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT, 404);
        }

        if (!$this->can(Users::ACCESS_LEVELS['VOLUNTEER'], $session->getUser(), $participant->getAccounts()->first())) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        if ($aid !== false) {
            if (!$assignment = $this->getDoctrine()->getRepository('App:Assignments')->findOneBy([
                'id'          => $aid,
                'participant' => $pid
            ])) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_ASSIGNMENT, 404);
            }
        } else {
            $assignment = null;
        }

        $caseNotes = $this->getDoctrine()->getRepository('App:CaseNotes')->findBy([
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
            $map = json_decode($fd->getForm()->getColumnsMap(), true);
            $fields = (static function () use ($map) {
                $fields = [];
                foreach ($map as $mapRow) {
                    $fields[$mapRow['name']] = $mapRow['value'];
                }
                return $fields;
            })();

            foreach ($fd->getValues() as $value) {
                $values[$value->getName()] = $value->getValue();
            }
        }

        $firstName = (isset($fields['first_name'], $values[$fields['first_name']])) ? $values[$fields['first_name']] : '';
        $lastName = (isset($fields['last_name'], $values[$fields['last_name']])) ? $values[$fields['last_name']] : '';
        $organizationId = (isset($fields['organization_id'], $values[$fields['organization_id']])) ? $values[$fields['organization_id']] : '';
        $systemId = $participant->getData()->getSystemId();

        $firstRow = $firstName . ' ' . $lastName . ', ' . $systemId . ', ' . $organizationId . ', ' . $this->convertDateTime($session->getUser());

        $data[] = [
            $type === 'csv' ? '"' . $firstRow . '"' : $firstRow
        ];

        $data[] = [];

        $data[] = [
            'Communication Type',
            'Communication Notes',
            'Timestamp',
            'Username'
        ];

        $types = $this->typesLabels();

        foreach ($caseNotes as $row) {
            $date = $row->getModifiedAt() ?: $row->getCreatedAt();

            $data[] = [
                $types[$row->getType()] ?? '',
                $type === 'csv' ? '"' . strip_tags($row->getNote()) . '"' : strip_tags($row->getNote()),
                $this->convertDateTime($session->getUser(), $date),
                $row->getModifiedBy() ? $row->getModifiedBy()->getData()->getFullName(false) : $row->getCreatedBy() ? $row->getCreatedBy()->getData()->getFullName(false) : 'System Administrator'
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


    /**
     * @param $data
     * @return Response
     */
    private function exportCsv($data): Response
    {
        $fileName = 'CommunicationNotes';

        return new Response(
            (Helper::csvConvert($data)),
            200,
            [
                'Content-Type'        => 'application/csv',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName . '.csv'),
            ]
        );
    }

    /**
     * @param $data
     * @return Response
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    private function exportXls($data): Response
    {
        $spreadsheet = new Spreadsheet();
        $writer = new Xlsx($spreadsheet);
        $sheet = $spreadsheet->getActiveSheet();
        $fileName = 'CommunicationNotes';

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
                'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName . '.xlsx')
            ]
        );
    }

    /**
     * @param $data
     * @return Response
     */
    private function exportPdf($data): Response
    {
        $firstRow = $data[0];
        $columns = $data[2];

        unset($data[0], $data[1], $data[2]);

        $html = $this->renderView('CaseNotes/pdf.html.twig', [
            'first_row' => $firstRow[0],
            'columns'   => $columns,
            'notes'     => $data
        ]);

        $fileName = 'CommunicationNotes';

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
