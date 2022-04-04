<?php


namespace App\Service;

use App\Entity\AccountClone;
use App\Entity\AccountMerge;
use App\Entity\Accounts;
use App\Utils\Helper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;

class AccountCloneService
{
    protected $em;
    protected $formHelper;

    public function __construct(EntityManagerInterface $em, FormSchemaHelper $formHelper)
    {
        $this->em = $em;
        $this->formHelper = $formHelper;
    }

    public function cloneAccount(Accounts $sourceAccount)
    {
        $accountClone = $this->em->getRepository('App:AccountClone')->findOneBy(['sourceAccount'=>$sourceAccount]);

        if (!$accountClone) {
            $accountClone = new AccountClone();
            $accountClone->setsourceAccount($sourceAccount);
            $this->em->persist($accountClone);
            $this->em->flush();
        }

        $clonedAccount = $accountClone->getClonedSourceAccount();
        $status = json_decode($accountClone->getStatus(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $status = [];
        }

        if (!$clonedAccount) {
            $sourceAccount = $accountClone->getSourceAccount();
            $clonedAccount = $this->cloneBaseAccount($sourceAccount);
            $accountClone->setClonedAccount($clonedAccount);
            $this->em->flush();
        }

        if (!$accountClone->getParticipantsMap() || $accountClone->getParticipantsMap() == '') {
            $participantsMap = $this->cloneParticipants($sourceAccount, $clonedAccount);
            $accountClone->setParticipantsMap(json_encode($participantsMap));
            $this->em->flush();
        } else {
            $participantsMap = json_decode($accountClone->getParticipantsMap(), true);
        }

        if (!$accountClone->getAssignmentsMap() || $accountClone->getAssignmentsMap() == '') {
            $assignmentsMap = $this->cloneAssignments($participantsMap);
            $accountClone->setAssignmentsMap(json_encode($assignmentsMap));
            $this->em->flush();
        } else {
            $assignmentsMap = json_decode($accountClone->getAssignmentsMap(), true);
        }

        if (!$accountClone->getFormsMap() || $accountClone->getFormsMap() == '') {
            $formsMap = $this->cloneForms($sourceAccount, $clonedAccount);
            $accountClone->setFormsMap(json_encode($formsMap));
            $this->em->flush();
        } else {
            $formsMap = json_decode($accountClone->getFormsMap(), true);
        }

        if (!isset($status['forms_data']) || $status['forms_data'] != 1) {
            $this->cloneFormsData($sourceAccount, $clonedAccount, $formsMap, $participantsMap, $assignmentsMap);
            $status['forms_data'] = 1;
            $accountClone->setStatus(json_encode($status));
            $this->em->flush();
        }

        if (!isset($status['activity_feed']) || $status['activity_feed'] != 1) {
            $this->cloneActivityFeed($sourceAccount, $clonedAccount, $participantsMap);
            $status['activity_feed'] = 1;
            $accountClone->setStatus(json_encode($status));
            $this->em->flush();
        }

        if (!isset($status['case_notes']) || $status['case_notes'] != 1) {
            $this->cloneCaseNotes($participantsMap);
            $status['case_notes'] = 1;
            $accountClone->setStatus(json_encode($status));
            $this->em->flush();
        }

        if (!isset($status['credentials']) || $status['credentials'] != 1) {
            $this->cloneCredentials($sourceAccount, $clonedAccount);
            $status['credentials'] = 1;
            $accountClone->setStatus(json_encode($status));
            $this->em->flush();
        }

        if (!isset($status['events']) || $status['events'] != 1) {
            $this->cloneEvents($sourceAccount, $clonedAccount, $participantsMap);
            $status['events'] = 1;
            $accountClone->setStatus(json_encode($status));
            $this->em->flush();
        }

        if (!isset($status['messages']) || $status['messages'] != 1) {
            $this->cloneMessages($participantsMap, $assignmentsMap);
            $status['messages'] = 1;
            $accountClone->setStatus(json_encode($status));
            $this->em->flush();
        }

        if (!isset($status['workspace_shared_files']) || $status['workspace_shared_files'] != 1) {
            $this->cloneWorkspaceFiles($sourceAccount, $clonedAccount);
            $status['workspace_shared_files'] = 1;
            $accountClone->setStatus(json_encode($status));
            $this->em->flush();
        }

        if (!isset($status['reports']) || $status['reports'] != 1) {
            $this->cloneReports($sourceAccount, $clonedAccount, $formsMap);
            $status['reports'] = 1;
            $accountClone->setStatus(json_encode($status));
            $this->em->flush();
        }

        return $clonedAccount;
    }

    private function cloneBaseAccount(Accounts $account)
    {
        $newAccountData = clone($account->getData());
        $newAccount = clone($account);

        $newAccountData->setAccountUrl('NEW!' . $account->getData()->getAccountUrl());
        $newAccount->setOrganizationName('NEW!' . $account->getOrganizationName());
        $newAccount->setSystemId($this->generateSystemId());
        $newAccount->setData($newAccountData);
        $this->em->persist($newAccount);
        $this->em->flush();

        foreach ($account->getUsers() as $accountUser) {
            $accountUser->addAccount($newAccount);
        }

        $this->em->flush();

        return $newAccount;
    }

    private function cloneAssignments(array $participantsMap)
    {
        $assignmentsMap = [];
        $conn = $this->em->getConnection();

        foreach ($participantsMap as $sourceParticipantId => $destinationParticipantId) {
            $assignments = $this->em->getRepository('App:Assignments')->findBy(['participant' => $sourceParticipantId]);

            foreach ($assignments as $srcAssignment) {
                $sql = "INSERT INTO `assignments` (primary_case_manager_id, participant_id, program_status_start_date, program_status_end_date, program_status, avatar) ".
                    "SELECT primary_case_manager_id, $destinationParticipantId, program_status_start_date, program_status_end_date, program_status, avatar ".
                    "FROM `assignments` WHERE participant_id = $sourceParticipantId";

                $stmt = $conn->prepare($sql);
                $stmt->execute();

                $newAssignmentId = $conn->lastInsertId();
                $assignmentsMap[$srcAssignment->getId()] = $newAssignmentId;
            }
        }

        return $assignmentsMap;
    }

    private function cloneParticipants(Accounts $sourceAccount, Accounts $destinationAccount)
    {
        $participants = $this->em->getRepository('App:Users')->findParticipantsForAccount($sourceAccount);

        $participantsIdsMap = [];

        foreach ($participants as $participant) {
            $participantId = $participant->getId();
            $newEmail = 'NEW!' . $participant->getEmail();
            $newUserName = 'NEW!' . $participant->getUsername();
            $newEmailCanonical = 'NEW!' . $participant->getEmailCanonical();
            $newUserNameCanonical = 'NEW!' . $participant->getUsernameCanonical();

            $sql = "INSERT INTO `users` (username, username_canonical, email, email_canonical, enabled, salt, password, last_login, confirmation_token, password_requested_at, roles, `type`, password_set_at, default_account, user_data_type) ".
                "SELECT '$newUserName', '$newUserNameCanonical', '$newEmail', '$newEmailCanonical', enabled, salt, password, last_login, confirmation_token, password_requested_at, roles, `type`, password_set_at, default_account, user_data_type ".
                "FROM `users` WHERE id = $participantId";

            $conn = $this->em->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute();

            $newUserId = $conn->lastInsertId();


            $sql = "INSERT INTO `users_data` (user_id, gender, system_id, case_manager, status_label, phone_number, date_birth, first_name, last_name, avatar, job_title, time_zone, date_completed, organization_id, status) ".
                "SELECT $newUserId, gender, system_id, case_manager, status_label, phone_number, date_birth, first_name, last_name, avatar, job_title, time_zone, date_completed, organization_id, status ".
                "FROM `users_data` WHERE user_id = $participantId";

            $stmt = $conn->prepare($sql);
            $stmt->execute();

            $sql = "INSERT INTO `users_settings` (user_id, `name`, `value`) ".
                "SELECT $newUserId, `name`, `value` ".
                "FROM `users_settings` WHERE user_id = $participantId";

            $stmt = $conn->prepare($sql);
            $stmt->execute();

            $participantsIdsMap[$participantId] = $newUserId;
        }

        return $participantsIdsMap;
    }


    public function removeAccount(Accounts $account)
    {
        $participants = $this->em->getRepository('App:Users')->findParticipantsForAccount($account);

        foreach ($participants as $participant) {
            $this->em->remove($participant);
        }

        $accountData = $account->getData();
        $this->em->remove($accountData);
        $this->em->remove($account);

        $this->em->flush();
    }

    private function cloneForms(Accounts $sourceAccount, Accounts $destinationAccount)
    {
        $formsMap = [];
        $sourceForms = $sourceAccount->getForms();
        foreach ($sourceForms as $sourceForm) {
            $sourceFormAccounts = $sourceForm->getAccounts();
            $newForm = clone($sourceForm);
            $newForm->clearAccounts();
            $this->em->persist($newForm);
            $this->em->flush();

            foreach ($sourceFormAccounts as $sourceFormAccount) {
                if ($sourceFormAccount != $sourceAccount) {
                    $newForm->addAccount($sourceFormAccount);
                }
            }

            $newForm->addAccount($destinationAccount);
            $this->em->flush();

            $formsMap[$sourceForm->getId()] = $newForm->getId();
        }

        return $formsMap;
    }

    private function cloneFormsData(Accounts $sourceAccount, Accounts $destinationAccount, array $formsMap, array $participantsMap, array $assignmentsMap)
    {
        $conn = $this->em->getConnection();
        $conn->setAutoCommit(false);
        $conn->beginTransaction();

        $formsData = $this->em->getRepository('App:FormsData')->findBy(['account_id' => $sourceAccount]);

        echo 'Cloning forms data...';

        $i=0;

        foreach ($formsData as $formData) {
            $i++;
            $formDataId = $formData->getId();
            $newFormId = $formsMap[$formData->getForm()->getId()];
            $newElementId = isset($participantsMap[$formData->getElementId()]) ? $participantsMap[$formData->getElementId()] : 0;
            $newAssignmentId = $formData->getAssignment() ? $assignmentsMap[$formData->getAssignment()->getId()] : 'NULL';
            $newAccountId = $destinationAccount->getId();

            $sql = "INSERT INTO `forms_data` (module_id, form_id, element_id, creator_id, editor_id, created_date, updated_date, manager_id, assignment_id, account_id) " .
                "SELECT module_id, $newFormId, $newElementId, creator_id, editor_id, created_date, updated_date, manager_id, $newAssignmentId, $newAccountId " .
                "FROM `forms_data` WHERE id = $formDataId";

            $stmt = $conn->prepare($sql);
            $stmt->execute();

            $newDataId = $conn->lastInsertId();

            $sql = "INSERT INTO `forms_values` (name, value, date, data_id) SELECT name, value, date, $newDataId FROM `forms_values` WHERE data_id = $formDataId";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            if ($i == 1000) {
                echo 'Inserted 1000 rows';
                $i = 0;
            }
        }

        $conn->commit();
    }


    private function generateSystemId()
    {
        $systemId = Helper::generateCode();

        while ($this->em->getRepository('App:Accounts')->findOneBy(['systemId' => $systemId])) {
            $systemId = Helper::generateCode();
        }

        return $systemId;
    }

    private function cloneActivityFeed(Accounts $oldAccount, Accounts $newAccount, array $participantsMap)
    {
        $conn = $this->em->getConnection();

        foreach ($participantsMap as $srcParticiapntId => $dstParticipantId) {
            $sql = "INSERT INTO `activity_feeds` (participant_id, template, created_at, title, template_id, account_id, details) " .
                "SELECT $dstParticipantId, template, created_at, title, template_id, account_id, details " .
                "FROM `activity_feeds` WHERE participant_id = $srcParticiapntId";

            $stmt = $conn->prepare($sql);
            $stmt->execute();
        }

        $oldAccountId = $oldAccount->getId();
        $newAccountId = $newAccount->getId();

        $sql = "INSERT INTO `activity_feeds` (template, created_at, title, template_id, account_id, details) " .
            "SELECT template, created_at, title, template_id, $newAccountId, details " .
            "FROM `activity_feeds` WHERE account_id = $oldAccountId";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }

    private function cloneCaseNotes(array $participantsMap)
    {
        $conn = $this->em->getConnection();

        foreach ($participantsMap as $srcParticiapntId => $dstParticipantId) {
            $sql = "INSERT INTO `case_notes` (created_by_id, modified_by_id, manager_id, participant_id, `type`, note, created_at, modified_at, assignment_id) " .
                "SELECT created_by_id, modified_by_id, manager_id, $dstParticipantId, `type`, note, created_at, modified_at, assignment_id " .
                "FROM `case_notes` WHERE participant_id = $srcParticiapntId";

            $stmt = $conn->prepare($sql);
            $stmt->execute();
        }
    }

    private function cloneCredentials(Accounts $srcAccount, Accounts $dstAccount)
    {
        $conn = $this->em->getConnection();
        $oldAccountId = $srcAccount->getId();
        $newAccountId = $dstAccount->getId();

        $sql = "INSERT INTO `credentials` (account_id, user_id, enabled, access, widgets, is_virtual) " .
            "SELECT $newAccountId, user_id, enabled, access, widgets, is_virtual " .
            "FROM `credentials` WHERE account_id = $oldAccountId";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }

    private function cloneEvents(Accounts $srcAccount, Accounts $dstAccount, $participantsMap)
    {
        $conn = $this->em->getConnection();
        $newAccountId = $dstAccount->getId();

        $events = $this->em->getRepository('App:Events')->findBy(['account' => $srcAccount]);

        foreach ($events as $event) {
            $newParticipantId = $event->getParticipant() ? $participantsMap[$event->getParticipant()->getId()] : 'NULL';

            $eventId = $event->getId();

            $sql = "INSERT INTO `events` (participant_id, created_by_id, modified_by_id, title, all_day, start_date_time, end_date_time, comment, created_at, modified_at, account_id) " .
                "SELECT $newParticipantId, created_by_id, modified_by_id, title, all_day, start_date_time, end_date_time, comment, created_at, modified_at, $newAccountId " .
                "FROM `events` WHERE id = $eventId";

            $stmt = $conn->prepare($sql);
            $stmt->execute();
        }
    }


    private function cloneMessages($participantsMap, $assignmentMap)
    {
        $conn = $this->em->getConnection();

        foreach ($participantsMap as $oldParticipantId => $newParticipantId) {
            foreach ($assignmentMap as $oldAssignmentId => $newAssignmentId) {
                $sql = "INSERT INTO `messages` (participant_id, user_id, assignment_id, from_phone, to_phone, body, `type`, status, created_at, sid, mass_message_id, error) " .
                    "SELECT $newParticipantId, user_id, $newAssignmentId, from_phone, to_phone, body, `type`, status, created_at, sid, mass_message_id, error " .
                    "FROM `messages` WHERE participant_id = $oldParticipantId AND assignment_id = $oldAssignmentId";

                $stmt = $conn->prepare($sql);
                $stmt->execute();
            }
        }
    }

    private function cloneWorkspaceFiles(Accounts $srcAccount, Accounts $dstAccount)
    {
        $conn = $this->em->getConnection();

        $oldAccountId = $srcAccount->getId();
        $newAccountId = $dstAccount->getId();

        $sql = "INSERT INTO `workspace_shared_file` (account_id, user_id, original_filename, server_filename, description, created_at, updated_at, deleted_at) " .
            "SELECT $newAccountId, user_id, original_filename, server_filename, description, created_at, updated_at, deleted_at " .
            "FROM `workspace_shared_file` WHERE account_id = $oldAccountId";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }


    private function cloneReports(Accounts $srcAccount, Accounts $dstAccount, $formsMap): void
    {
        $conn = $this->em->getConnection();

        $srcAccountId = $srcAccount->getId();
        $dstAccountId = $dstAccount->getId();


        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('folder_id', 'folder_id');
        $rsm->addScalarResult('tree_root', 'tree_root');
        $rsm->addScalarResult('parent_id', 'parent_id');
        $rsm->addScalarResult('folder_name', 'folder_name');
        $rsm->addScalarResult('lft', 'lft');
        $rsm->addScalarResult('lvl', 'lvl');
        $rsm->addScalarResult('rgt', 'rgt');

        $sql = "SELECT DISTINCT (f.id) AS folder_id, tree_root, parent_id, f.name AS folder_name, f.lft, f.lvl, f.rgt FROM reports r INNER JOIN reports_folders f ON r.report_folder_id = f.id AND r.account_id = $srcAccountId ORDER BY f.lft";

        $query = $this->em->createNativeQuery($sql, $rsm);
        $results = $query->getResult();

        $foldersMap = [];

        foreach ($results as $result) {
            if ($result['folder_name'] == 'account'.$srcAccountId) {
                $name = 'account'.$dstAccountId;
                $newRoot = 'NULL';
                $parentId = 'NULL';
            } else {
                $name = $result['folder_name'];
            }

            $resultId =  $result['folder_id'];

            $sql = "INSERT INTO `reports_folders` (tree_root, parent_id, `name`, lft, lvl, rgt) " .
                "SELECT $newRoot, $parentId, '$name', lft, lvl, rgt " .
                "FROM `reports_folders` WHERE id = $resultId";

            $stmt = $conn->prepare($sql);
            $stmt->execute();

            $foldersMap[$result['folder_id']] = $conn->lastInsertId();

            if ($name == 'account'.$dstAccountId) {
                $newRoot = $conn->lastInsertId();
                $sql = "UPDATE `reports_folders` SET tree_root = $newRoot WHERE id = $newRoot";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $parentId = $newRoot;
            }
        }


        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('data', 'data');
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('description', 'description');
        $rsm->addScalarResult('report_folder_id', 'report_folder_id');

        $sql = "SELECT * FROM `reports` WHERE `account_id` = $srcAccountId";

        $query = $this->em->createNativeQuery($sql, $rsm);
        $results = $query->getResult();


        foreach ($results as $report) {
            $reportId = $report['id'];
            $data = $report['data'];
            $newFolderId = $foldersMap[$report['report_folder_id']];

            $data = json_decode($data, true);

            foreach ($data as $formsData) {
                $newFormsData = $formsData;
                $newFormsData['form_id'] = $formsMap[$formsData['form_id']];

                if (is_array($formsData['fields'])) {
                    $newFields = [];
                    foreach ($formsData['fields'] as $formDataField) {
                        $newField = $formDataField;
                        if (isset($formDataField['id'])) {
                            $newField['id'] = $formsMap[$formDataField['id']];
                        }
                        $newFields[] = $newField;
                    }
                    $newFormsData['fields'] = $newFormsData;
                }
            }

            $this->summary['reports'][] = $report['name'] . ' (' . $report['description'] . ')';

            $newReportData = addslashes(json_encode($newFormsData));

            $sql = "INSERT INTO `reports` (user_id, `name`, description, created_date, status, `type`, `data`, account_id, accounts, results_count, report_folder_id, modified_date, date_format) " .
                "SELECT user_id, `name`, description, created_date, status, `type`, '$newReportData', $dstAccountId, accounts, results_count, $newFolderId, modified_date, date_format " .
                "FROM `reports` WHERE id = $reportId";

            $stmt = $conn->prepare($sql);
            $stmt->execute();
        }
    }
}
