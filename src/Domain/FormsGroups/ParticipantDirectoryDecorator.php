<?php

namespace App\Domain\FormsGroups;

use App\Entity\Accounts;
use App\Service\Participants\ParticipantDirectoryServiceInterface;
use Doctrine\Persistence\ManagerRegistry;
use Dompdf\Exception;

class ParticipantDirectoryDecorator implements ParticipantDirectoryServiceInterface
{
    private ParticipantDirectoryServiceInterface $participantDirectoryService;
    private ManagerRegistry $doctrine;
    private int $formId;

    public function __construct(
        ParticipantDirectoryServiceInterface $participantDirectoryService,
        ManagerRegistry $doctrine
    )
    {
        $this->participantDirectoryService = $participantDirectoryService;
        $this->doctrine = $doctrine;
    }

    public function setFormId(int $formId)
    {
        $this->formId = $formId;
    }

    public function search(): array
    {
        $resultRows = $this->participantDirectoryService->search();

        $participantsIds = array_column($resultRows, 'id');

        $form = $this->doctrine->getRepository('App:Forms')->find($this->formId);

        if (!$form) {
            throw new Exception('Invalid form');
        }

        if ($form->getMultipleEntries()) {
            foreach ($resultRows as &$row) {
                $row['disabled'] = false;
            }
            return $resultRows;
        }

        $formData = $this->doctrine->getRepository('App:FormsData')->findByFormAndParticipants($this->formId, $participantsIds);
        $participantWithFilledForms = array_column($formData, 'element_id');

        foreach ($resultRows as &$row) {
            if (in_array($row['id'], $participantWithFilledForms)) {
                $row['disabled'] = true;
            } else {
                $row['disabled'] = false;
            }
        }

        return $resultRows;
    }

    public function paginate($dql, int $page = 1, int $limit = 20)
    {
        return $this->participantDirectoryService->paginate($dql, $page, $limit);
    }

    public function set(string $name, $value)
    {
        return $this->participantDirectoryService->set($name, $value);
    }

    public function has(string $name): bool
    {
        return $this->participantDirectoryService->has($name);
    }

    public function setOrderBy(string $orderBy): void
    {
        $this->participantDirectoryService->setOrderBy($orderBy);
    }

    public function setOrderDir(string $orderDir): void
    {
        $this->participantDirectoryService->setOrderDir($orderDir);
    }

    public function getColumnFilter(): array
    {
        return $this->participantDirectoryService->getColumnFilter();
    }

    public function setColumnFilter(array $columnFilter): void
    {
        $this->participantDirectoryService->setColumnFilter($columnFilter);
    }

    public function setFilterProgramStatus(array $programStatuses): void
    {
        $this->participantDirectoryService->setFilterProgramStatus($programStatuses);
    }

    public function getDefaultColumns(): array
    {
        return $this->participantDirectoryService->getDefaultColumns();
    }

    public function getColumnsIfSearchInMultipleAccounts(int $startingPosition): array
    {
        return $this->participantDirectoryService->getColumnsIfSearchInMultipleAccounts($startingPosition);
    }

    public function getColumns(?Accounts $account = null, bool $withoutEmpty = false): array
    {
        return $this->participantDirectoryService->getColumns($account, $withoutEmpty);
    }

    public function getCustomFormColumns(Accounts $account): array
    {
        return $this->participantDirectoryService->getCustomFormColumns($account);
    }

    public function setAccounts($accounts)
    {
        return $this->participantDirectoryService->setAccounts($accounts);
    }

    public function saveParticipantDirectoryColumns(Accounts $account, string $columns): void
    {
        $this->participantDirectoryService->saveParticipantDirectoryColumns($account, $columns);
    }

    public function getContext(): ?string
    {
        return $this->participantDirectoryService->getContext();
    }

    public function setContext(string $context): void
    {
        $this->participantDirectoryService->setContext($context);
    }

    public function getDateFormat(): string
    {
        return $this->participantDirectoryService->getDateFormat();
    }

    public function setDateFormat(string $dateFormat): void
    {
        $this->participantDirectoryService->setDateFormat($dateFormat);
    }

    public function resultsNum(): int
    {
        return $this->participantDirectoryService->resultsNum();
    }


}
