<?php

namespace App\Service\Participants;

use App\Entity\Accounts;

interface ParticipantDirectoryServiceInterface
{
    public function paginate($dql, int $page = 1, int $limit = 20);
    public function set(string $name, $value);
    public function has(string $name): bool;
    public function setOrderBy(string $orderBy): void;
    public function setOrderDir(string $orderDir): void;
    public function getColumnFilter(): array;
    public function setColumnFilter(array $columnFilter): void;
    public function setFilterProgramStatus(array $programStatuses): void;
    public function getDefaultColumns(): array;
    public function getColumnsIfSearchInMultipleAccounts(int $startingPosition): array;
    public function getColumns(?Accounts $account = null, bool $withoutEmpty = false): array;
    public function getCustomFormColumns(Accounts $account): array;
    public function setAccounts($accounts);
    public function saveParticipantDirectoryColumns(Accounts $account, string $columns): void;
    public function getContext(): ?string;
    public function setContext(string $context): void;
    public function getDateFormat(): string;
    public function setDateFormat(string $dateFormat): void;
    public function search(): array;
    public function resultsNum(): int;
}
