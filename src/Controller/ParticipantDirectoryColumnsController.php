<?php


namespace App\Controller;

use App\Enum\ParticipantDirectoryContext;
use App\Enum\ParticipantType;
use App\Exception\ExceptionMessage;
use App\Service\Participants\IndividualsDirectoryService;
use App\Service\Participants\MembersDirectoryService;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use function Sentry\captureException;

/**
 * Class ParticipantDirectoryColumnsController
 * @package App\Controller
 */
class ParticipantDirectoryColumnsController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function availableColumnsAction(
        Request $request,
        IndividualsDirectoryService $individualsDirectoryService,
        MembersDirectoryService $membersDirectoryService
    ): JsonResponse
    {

        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->account()->getParticipantType() == ParticipantType::INDIVIDUAL) {
            $directoryService = $individualsDirectoryService;
        }

        if ($this->account()->getParticipantType() == ParticipantType::MEMBER) {
            $directoryService = $membersDirectoryService;
        }

        $context = $request->query->get('context');

        if (!ParticipantDirectoryContext::isValidValue($context)) {
            $context = ParticipantDirectoryContext::PARTICIPANT_DIRECTORY;
        }

        $directoryService->setContext($context);

        try {
            $directoryColumns = $directoryService->getDefaultColumns();
            $customColumns    = $directoryService->getCustomFormColumns($this->account());

            $data = [];

            foreach ($directoryColumns as $column) {
                $column['custom'] = false;
                unset($column['position']);
                $data[] = $column;
            }

            foreach ($customColumns as $column) {
                $column['custom'] = true;
                $column['field'] = $column['value'];
                unset($column['position']);
                unset($column['value']);
                $data[] = $column;
            }

            return $this->getResponse()->success([
                'columns' => $data
            ]);
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }
    }

    /**
     * @return JsonResponse
     */
    public function currentColumnsAction(
        Request $request,
        IndividualsDirectoryService $individualsDirectoryService,
        MembersDirectoryService $membersDirectoryService
    ): JsonResponse
    {

        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->account()->getParticipantType() == ParticipantType::INDIVIDUAL) {
            $directoryService = $individualsDirectoryService;
            $extraEmptyColumns = [8,9];
        }

        if ($this->account()->getParticipantType() == ParticipantType::MEMBER) {
            $directoryService = $membersDirectoryService;
            $extraEmptyColumns = [6,7];
        }

        $context = $request->query->get('context');

        if (!ParticipantDirectoryContext::isValidValue($context)) {
            $context = ParticipantDirectoryContext::PARTICIPANT_DIRECTORY;
        }

        $directoryService->setContext($context);
        $columns = $directoryService->getColumns($this->account());
        $data    = [];

        foreach ($columns as $column) {
            $data[$column['position']] = $column;
        }

        $empty = [
            'label'  => '',
            'field' => '',
            'sticky' => false,
            'custom' => true
        ];

        foreach ($extraEmptyColumns as $extraEmptyColumn) {
            if (! isset($data[$extraEmptyColumn])) {
                $empty['position'] = 8;
                $data[$extraEmptyColumn] = $empty;
            }
        }

        return $this->getResponse()->success([
            'columns' => $data
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function saveAction(
        IndividualsDirectoryService $individualsDirectoryService,
        MembersDirectoryService $membersDirectoryService
    ): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $columns = $this->getRequest()->param('columns');
        $context = $this->getRequest()->param('context');

        if ($this->account()->getParticipantType() == ParticipantType::INDIVIDUAL) {
            $directoryService = $individualsDirectoryService;
        }

        if ($this->account()->getParticipantType() == ParticipantType::MEMBER) {
            $directoryService = $membersDirectoryService;
        }

        $directoryService->setContext($context);
        $directoryService->saveParticipantDirectoryColumns($this->account(), $columns);

        return $this->getResponse()->success([]);
    }
}
