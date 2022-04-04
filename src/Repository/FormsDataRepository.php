<?php

namespace App\Repository;

use App\Entity\Accounts;
use App\Entity\Assignments;
use App\Entity\Forms;
use App\Entity\Users;
use App\Enum\ParticipantStatus;
use App\Entity\Modules;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr\Join;

/**
 * FormsDataRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class FormsDataRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @param string $module
     * @param Users $user
     * @param Accounts $account
     *
     * @return array
     */
    public function getByModuleAndElementId(string $module, Users $user, Accounts $account): array
    {
        $data = [];
        $qb = $this->createQueryBuilder('fd');

        $qb->leftJoin('App\Entity\Forms', 'f', 'WITH', 'f.id = fd.form');
        $qb->leftJoin('App\Entity\Modules', 'm', 'WITH', 'm.id = fd.module');
        $qb->innerJoin('App\Entity\Accounts', 'a', 'WITH', 'a.id = :account');
        $qb->setParameter('account', $account->getId());

        $qb->where('m.key = :module');
        $qb->andWhere('fd.element_id = :element_id');
        $qb->andWhere('fd.assignment IS NULL');

        $qb->orderBy('fd.created_date', 'DESC');
        $qb->setMaxResults(20);

        $qb->setParameter('module', $module);
        $qb->setParameter('element_id', (string)$user->getId());


        foreach ($qb->getQuery()->getResult() as $row) {
            $form = $row->getForm();

            $data[] = [
                'id'                 => $row->getId(),
                'creator'            => $row->getCreator() ? $row->getCreator()->getData()->getFullName() : 'System Administrator',
                'created_date'       => $row->getCreatedDate(),
                'editor'             => $row->getEditor() ? $row->getEditor()->getData()->getFullName() : 'System Administrator',
                'updated_date'       => $row->getUpdatedDate(),
                'form_id'            => $form->getId(),
                'form_name'          => $form->getName(),
                'case_manager'       => $row->getManager() ? $row->getManager()->getData()->getFullName() : null,
                'shared_form_status' => $row->getSharedForm() ? $row->getSharedForm()->getStatus() : null
            ];
        }

        return $data;
    }

    /**
     * CASE-667 v5
     *
     * @param $account_id
     * @return array
     */
    public function getClonedProfiles($account_id)
    {
        $profiles = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('fd.id') // formData id
            ->from('App\Entity\FormsData', 'fd')
            ->where('fd.module = :profile') // tam gdzie modul to profil
            ->setParameter('profile', 1)
            ->leftJoin('fd.form', 'f')
            ->innerJoin('f.accounts', 'a')
            ->andwhere('a.id = :account')
            ->setParameter('account', $account_id)
            ->andWhere('fd.assignment IS NULL')
            // wykluczamy profile których participanci mają status 'Dismissed'
            ->innerJoin('App\Entity\UsersData', 'ud', 'WITH', 'ud.user = fd.element_id')
            ->andWhere('ud.status = :dismissed')
            ->setParameter('dismissed', ParticipantStatus::DISMISSED)
            ->getQuery()
            ->getArrayResult();

        $ids = [];

        foreach ($profiles as $profile) {
            $ids[] = (int)$profile['id'];
        }

        return $ids;
    }

    /**
     * @param $account
     * @return mixed
     */
    public function getAllByAccount($account)
    {
        return $this->createQueryBuilder('fd')
            ->leftJoin('fd.form', 'f')
            ->innerJoin('fd.accounts', 'a')
            ->where('a.id = :account')
            ->setParameter('account', $account)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all forms data for module, used in Workspace
     *
     * @param string $module
     * @param Accounts $account
     * @param $accessLevel
     *
     * @return array
     */
    public function getByModuleAccountAndAccessLevel(string $module, Accounts $account, int $accessLevel = null): array
    {
        $data = [];
        $qb = $this->createQueryBuilder('fd');

        $qb->leftJoin('App\Entity\Forms', 'f', 'WITH', 'f.id = fd.form');
        $qb->leftJoin('App\Entity\Modules', 'm', 'WITH', 'm.id = fd.module');

        $qb->where('m.key = :module');
        $qb->andWhere('fd.account_id = :accountId');

        if ($accessLevel) {
            $qb->andWhere('f.accessLevel <= :accessLevel');
            $qb->setParameter('accessLevel', $accessLevel);
        }

        $qb->orderBy('fd.created_date', 'DESC');
        $qb->setMaxResults(20);

        $qb->setParameter('module', $module);
        $qb->setParameter('accountId', $account);

        foreach ($qb->getQuery()->getResult() as $row) {
            $form = $row->getForm();

            $data[] = [
                'id'           => $row->getId(),
                'creator'      => $row->getCreator() ? $row->getCreator()->getData()->getFullName() : 'System Administrator',
                'created_date' => $row->getCreatedDate(),
                'editor'       => $row->getEditor() ? $row->getEditor()->getData()->getFullName() : 'System Administrator',
                'updated_date' => $row->getUpdatedDate(),
                'form_id'      => $form->getId(),
                'form_name'    => $form->getName(),
                'case_manager' => $row->getManager() ? $row->getManager()->getData()->getFullName() : null
            ];
        }

        return $data;
    }

    public function findByFormAndAccount($form, $account)
    {
        return $qb = $this->createQueryBuilder('fd')
            ->andWhere('fd.form = :form')
            ->andWhere('fd.account_id = :account')
            ->setParameter('form', $form)
            ->setParameter('account', $account)
            ->getQuery()
            ->getResult();
    }

    public function findIdsByForm(Forms $forms)
    {
        $qb = $this->createQueryBuilder('fd')
            ->select('fd.id')
            ->andWhere('fd.form IN (:forms)')
            ->setParameter('forms', $forms)
            ->getQuery()
            ->getArrayResult();

        return array_column($qb, 'id');
    }

    public function findByFormsAndAccountsIgnoreIds($forms, $accounts, $ignoreIds)
    {
        $qb = $this->createQueryBuilder('fd')
            ->andWhere('fd.form IN (:forms)')
            ->setParameter('forms', $forms)
            ->andWhere('fd.account_id IN (:accounts)')
            ->setParameter('accounts', $accounts);

        if (count($ignoreIds)) {
            $qb = $qb->andWhere('fd.id NOT IN (:ignoreIds)')
                ->setParameter('ignoreIds', $ignoreIds);
        }

        $qb = $qb->getQuery();

        return $qb->getResult();
    }

    public function findCoreIds($forms, $accounts, $assignmentsIds, $participantsIds, $formsDataIds, $ignoreIds)
    {
        $qb = $this->createQueryBuilder('fd')
            ->andWhere('fd.form IN (:forms)')
            ->setParameter('forms', $forms)
            ->andWhere('fd.account_id IN (:accounts)')
            ->setParameter('accounts', $accounts)
            ->andWhere('fd.id NOT IN (:ignoreIds)')
            ->setParameter('ignoreIds', $ignoreIds)
            ->andWhere('fd.assignment IN (:assignmentsIds) OR fd.assignment IS NULL')
            ->setParameter('assignmentsIds', $assignmentsIds)
            ->andWhere('fd.element_id IN (:participantsIds)')
            ->setParameter('participantsIds', $participantsIds)
            ->getQuery();

        return $qb->getResult();
    }

    public function updateModule(Forms $form, ?Modules $module)
    {
        $moduleId = $module ? $module->getId() : 'NULL';

        return $qb = $this->createQueryBuilder('fd')
            ->update()
            ->set('fd.module', $moduleId)
            ->where('fd.form = :form')
            ->setParameter('form', $form)
            ->getQuery()
            ->execute();
    }


    public function findByFormAndParticipants($form, array $participantsIds): array
    {
        return $qb = $this->createQueryBuilder('fd')
            ->andWhere('fd.form = :form')
            ->andWhere('fd.element_id IN (:participantsIds)')
            ->setParameter('form', $form)
            ->setParameter('participantsIds', $participantsIds)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);
    }

    public function findForParticipantAssignmentAccountDateRange(?int $participantId, ?int $assignmentId, Accounts $accounts, Forms $form, \DateTime $dateFrom, \DateTime $dateTo)
    {
        $qb = $this->createQueryBuilder('fd')
            ->andWhere('fd.form = :form')
            ->andWhere('fd.account_id = :account')
            ->andWhere('fd.element_id = :participantsId')
            ->andWhere('fd.created_date >= :minDate')
            ->andWhere('fd.created_date <= :maxDate')
            ->setParameter('form', $form)
            ->setParameter('account', $accounts)
            ->setParameter('participantsId', $participantId)
            ->setParameter('minDate', $dateFrom->setTime(0, 0)->format('Y-m-d H:i:s'))
            ->setParameter('maxDate', $dateTo->setTime(23, 59, 59)->format('Y-m-d H:i:s'));

        if ($assignmentId) {
            $qb->andWhere('fd.assignment = :assignment')->setParameter('assignment', $assignmentId);
        } else {
            $qb->andWhere('fd.assignment IS NULL');
        }

        return $qb->getQuery()->getResult();
    }


    public function findForParticipantAssignmentAccountFieldDateRange(?int $participantId, ?int $assignmentId, Accounts $accounts, Forms $form, \DateTime $dateFrom, \DateTime $dateTo, string $fieldName)
    {
        $dateFrom = $dateFrom->format('Y-m-d');
        $dateTo = $dateTo->format('Y-m-d');

        $formId = $form->getId();

        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT fd.id FROM forms_data fd
    JOIN forms_values fv ON fd.id = fv.data_id
    WHERE fv.name = '$fieldName'
    AND fd.form_id = $formId
    AND (STR_TO_DATE(fv.value, '%m/%d/%Y') BETWEEN '$dateFrom' AND '$dateTo')";


        if ($participantId !== null) {
            $sql .= " AND fd.element_id = $participantId";
        }

        if ($assignmentId !== null) {
            $sql .= " AND fd.assignment_id = $assignmentId";
        } else {
            $sql .= " AND fd.assignment_id IS NULL";
        }

        $res = $conn->fetchAllAssociative($sql);

        $ids = array_column($res, 'id');

        return $ids;
    }

    public function findIdsForFormAccountParticipantUserIdAssignment(Forms $form, Accounts $accounts, ?int $participantUserId, ?Assignments $assignment): array
    {
        $qb = $this->createQueryBuilder('fd')
            ->select('fd.id')
            ->where('fd.form = :form')
            ->andWhere('fd.account_id = :account')
            ->andWhere('fd.element_id = :participantUserId')
            ->setParameter('form', $form)
            ->setParameter('account', $accounts)
            ->setParameter('participantUserId', $participantUserId);

        if ($assignment) {
            $qb->setParameter('assignment', $assignment)->andWhere('fd.assignment = :assignment');
        } else {
            $qb->andWhere('fd.assignment IS NULL');
        }

        $formsData = $qb->getQuery()
            ->getArrayResult();

        return array_column($formsData, 'id');
    }
}
