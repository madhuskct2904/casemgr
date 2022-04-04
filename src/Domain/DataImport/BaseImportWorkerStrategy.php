<?php


namespace App\Domain\DataImport;


use App\Entity\Accounts;
use App\Entity\Users;
use App\Utils\Helper;

abstract class BaseImportWorkerStrategy implements ImportWorkerStrategyInterface
{

    protected $em;
    protected $importHandler;
    protected $user;
    protected $account;

    public function getImportHandler(): ImportHandlerInterface
    {
        return $this->importHandler;
    }

    public function setAccount(Accounts $accounts)
    {
        $this->account = $accounts;
    }

    public function getAccount(): Accounts
    {
        return $this->account;
    }


    public function setUser(Users $user): void
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    protected function findParticipantIdByKeyField(int $formId, string $field, string $value): int
    {
        if (!$this->account) {
            throw new ImportWorkerException('Account is not set! Can\'t search in participant directory');
        }

        $accountId = $this->getAccount()->getId();

        $conn = $this->em->getConnection();
        $sql = "SELECT element_id FROM forms_values fv JOIN forms_data fd ON fv.data_id = fd.id WHERE fd.form_id = $formId AND fd.account_id = $accountId AND fv.value = '$value' AND fv.name = '$field'";
        $results = $conn->fetchAllAssociative($sql);

        $participantUserId = array_unique(array_column($results, 'element_id'));

        if (count($participantUserId) > 1) {
            throw new ImportWorkerException('Found more than one more matching participants!');
        }

        if (count($participantUserId) === 0) {
            throw new ImportWorkerException('Participant not found: [' . $field . '] ' . $value);
        }

        return $participantUserId[0];
    }

    protected function getKeyFieldValue(array $csvRow): ?string
    {
        $keyField = $this->getImportHandler()->getImportKeyField();

        if (!isset($keyField['colInFile'])) {
            return null;
        }

        return $csvRow[$keyField['colInFile']];
    }

    protected function parseCsvRow(array $csvRow): array
    {
        $row = [];

        $map = $this->getImportHandler()->getImportFieldsMap();

        foreach ($map as $colIdxInFile => $fieldName) {
            if (!$fieldName) {
                continue;
            }

            $row[$fieldName] = $csvRow[$colIdxInFile];
        }

        return $row;
    }

    protected function getCompletedAt(array $row): \DateTime
    {
        if (isset($row['import-completed-date']) && $row['import-completed-date'] && (bool)strtotime($row['import-completed-date'])) {
            $importCompletedDate = new \DateTime($row['import-completed-date']);
        } else {
            $importCompletedDate = new \DateTime();
        }

        return $importCompletedDate;
    }
}
