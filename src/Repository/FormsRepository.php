<?php

namespace App\Repository;

use App\Entity\Accounts;
use App\Entity\Forms;
use App\Entity\FormsHistory;
use App\Entity\Modules;
use App\Entity\Users;
use App\EntityRepository\EntityRepository;
use App\Enum\AccountType;
use App\Enum\FormType;

/**
 * Class FormsRepository
 *
 * @package App\Repository
 */
class FormsRepository extends EntityRepository
{
    public function save(
        Users $user,
        string $name,
        string $description,
        string $schema,
        string $conditionals,
        string $systemConditionals,
        string $updateConditionals,
        string $calculations,
        string $columnsMap,
        string $type,
        ?Modules $module,
        bool $allowMultipleEntries,
        ?Accounts $account,
        $accessLevel = null,
        string $hideValues = null,
        bool $captchaEnabled = false
    ): Forms {
        $em = $this->getEntityManager();

        $form = new Forms();

        $form->setName($name);
        $form->setDescription($description);
        $form->setData($this->stripSpacesFromSchema($schema));
        $form->setUser($user);
        $form->setType($type);
        $form->setCreatedDate(new \DateTime());
        $form->setConditionals($conditionals);
        $form->setSystemConditionals($systemConditionals);
        $form->setCalculations($calculations);
        $form->setPublish(false);
        $form->setColumnsMap($columnsMap);
        $form->setModule($module);
        $form->setMultipleEntries($allowMultipleEntries);
        $form->setHideValues($hideValues);
        $form->setStatus(false);
        $form->setCaptchaEnabled($captchaEnabled);
        $form->setUpdateConditionals($updateConditionals);

        if ($module && $module->getRole() == 'referral') {
            $form->setShareUid(bin2hex(openssl_random_pseudo_bytes(20)));
            $form->setCaptchaEnabled(true);
        }

        if ($account) {
            $form->addAccount($account);
        }

        if ($type === FormType::FORM) {
            if ($accessLevel) {
                $form->setAccessLevel($accessLevel);
            } elseif (in_array($module->getKey(), ['activities_services', 'assessment_outcomes'])) {
                $form->setAccessLevel(Users::ACCESS_LEVELS['VOLUNTEER']);
            }
        }

        $em->persist($form);
        $em->flush();

        $this->saveHistory($user, $name, $schema, $conditionals, $form);

        $em->refresh($form);

        return $form;
    }


    public function update(
        Forms $form,
        Users $user,
        string $name,
        string $description,
        string $schema,
        string $conditionals,
        string $extraValidationRules,
        string $systemConditionals,
        string $updateConditionals,
        string $calculations,
        string $columns_map,
        string $type,
        ?Modules $module,
        $multipleEntries = false,
        $setGlobal = false,
        $accessLevel = null,
        string $hideValues = null,
        string $customColumns = null,
        bool $captchaEnabled = false,
        bool $shareWithParticipant = false,
        bool $hasSharedFields = false
    ): Forms {
        $em = $this->getEntityManager();

        $form->setName($name);
        $form->setDescription($description);
        $form->setData($this->stripSpacesFromSchema($schema));
        $form->setType($type);
        $form->setLastActionUser($user);
        $form->setLastActionDate(new \DateTime());
        $form->setConditionals($conditionals);
        $form->setExtraValidationRules($extraValidationRules);
        $form->setSystemConditionals($systemConditionals);
        $form->setCalculations($calculations);
        $form->setColumnsMap($columns_map);
        $form->setModule($module);
        $form->setMultipleEntries($multipleEntries);
        $form->setHideValues($hideValues);
        $form->setCustomColumns($customColumns);
        $form->setShareWithParticipant($shareWithParticipant);
        $form->setHasSharedFields($hasSharedFields);
        $form->setCaptchaEnabled($captchaEnabled);
        $form->setUpdateConditionals($updateConditionals);

        if ($setGlobal) {
            $form->clearAccounts();
        }

        if ($accessLevel) {
            $form->setAccessLevel($accessLevel);
        }

        $em->persist($form);
        $em->flush();

        $this->saveHistory($user, $name, $schema, $conditionals, $form);

        $em->refresh($form);

        return $form;
    }

    public function saveHistory(Users $user, string $name, string $data, string $conditionals, Forms $form): FormsHistory
    {
        $em = $this->getEntityManager();

        $formHistory = new FormsHistory();
        $formHistory->setUser($user);
        $formHistory->setName($name);
        $formHistory->setData($data);
        $formHistory->setForm($form);
        $formHistory->setDate(new \DateTime());
        $formHistory->setConditionals($conditionals);

        $em->persist($formHistory);
        $em->flush();
        $em->refresh($formHistory);

        return $formHistory;
    }

    public function findOneByIdAccountAccessLevel(int $id, Accounts $account, int $accessLevel): ?Forms
    {
        $qb = $this->createQueryBuilder('f');

        $qb->where("f.id = $id");

        if ($accessLevel === Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
            return $qb->getQuery()->getOneOrNullResult();
        }

        if ($accessLevel === Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'] && $account->getAccountType() === AccountType::PARENT) {
            $accountsIds[] = $account->getId();

            foreach ($account->getChildrenAccounts() as $childrenAccount) {
                $accountsIds[] = $childrenAccount->getId();
            }

            $qb->innerJoin('f.accounts', 'a')
                ->andWhere("a.id IN (:accountsIds)")
                ->setParameter('accountsIds', $accountsIds);

            return $qb->getQuery()->getOneOrNullResult();
        }

        $qb->innerJoin('f.accounts', 'a')
            ->andWhere("a.id = :accountId")
            ->setParameter('accountId', $account->getId())
            ->andWhere('f.accessLevel <= :accessLevel OR f.accessLevel IS NULL')
            ->setParameter('accessLevel', $accessLevel);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAllByAccountAccessModules(Accounts $account, int $accessLevel = 0, array $modulesIds = [], $includeTemplates = true)
    {
        $qb = $this->createQueryBuilder('f');

        if ($includeTemplates === false) {
            $qb->where('f.type = :formType')
                ->setParameter('formType', 'form');
        }

        if (count($modulesIds)) {
            $qb->innerJoin('f.module', 'm')
                ->andWhere('m.id IN (:modules)')
                ->setParameter('modules', $modulesIds);
        }

        if ($accessLevel === Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
            $qb->leftJoin('f.accounts', 'a')
                ->andWhere('(f.type = :template AND a.id = :accountId) OR (f.type = :template AND a.id IS NULL) OR (f.type = :form AND a.id = :accountId)')
                ->setParameter('template', 'template')
                ->setParameter('form', 'form')
                ->setParameter('accountId', $account->getId());
        } else {
            $qb->innerJoin('f.accounts', 'a')
                ->andWhere('a.id = :accountId')
                ->setParameter('accountId', $account->getId())
                ->andWhere('f.accessLevel <= :accessLevel')
                ->setParameter('accessLevel', $accessLevel);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByModuleAndAccount(Modules $module, Accounts $account, bool $includeTemplates = true, bool $publishedOnly = false)
    {
        $qb = $this->createQueryBuilder('f')
            ->innerJoin('f.accounts', 'a')
            ->andWhere('a.id = :account')
            ->andWhere('f.module = :module')
            ->setParameter('account', $account->getId())
            ->setParameter('module', $module);

        if (!$includeTemplates) {
            $qb->andWhere('f.type = :formType')
                ->setParameter('formType', 'form');
        }

        if ($publishedOnly) {
            $qb->andWhere('f.publish = :publish')
                ->setParameter('publish', '1');
        }

        return $qb->getQuery()->getResult();
    }

    public function findByModuleAccountsAccessLevel(Modules $module, array $accounts, int $accessLevel, bool $publishedOnly = false)
    {
        $qb = $this->createQueryBuilder('f')
            ->innerJoin('f.accounts', 'a')
            ->andWhere('a.id IN (:accounts)')
            ->andWhere('f.module = :module')
            ->andWhere('f.accessLevel <= :accessLevel')
            ->andWhere('f.type = :formType')
            ->setParameter('accounts', $accounts)
            ->setParameter('module', $module)
            ->setParameter('accessLevel', $accessLevel)
            ->setParameter('formType', 'form');

        if ($publishedOnly) {
            $qb->andWhere('f.publish = 1');
        }

        return $qb->getQuery()->getResult();
    }

    public function findByAccount(Accounts $account)
    {
        $qb = $this->createQueryBuilder('f')
            ->innerJoin('f.accounts', 'a')
            ->andWhere('a.id = :account')
            ->andWhere('f.type = :formType')
            ->setParameter('account', $account->getId())
            ->setParameter('formType', 'form');

        return $qb->getQuery()->getResult();
    }

    public function findAllForAccountAndAccessLevel(Accounts $account, $accessLevel = 0, $includeTemplates = false)
    {
        $qb = $this->createQueryBuilder('f');

        $accounts = [$account->getId()];

        if ($account->getAccountType() == AccountType::PARENT) {
            $childAccounts = $account->getChildrenAccounts();
            foreach ($childAccounts as $account) {
                $accounts[] = $account->getId();
            }
        }

        if ($includeTemplates === false) {
            $qb->where('f.type = :formType')
                ->setParameter('formType', 'form');
        }

        if ($accessLevel == Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
            $qb->leftJoin('f.accounts', 'a')
                ->andWhere('f.type = :form AND a.id IN (:accountsIds)')
                ->setParameter('form', 'form')
                ->setParameter('accountsIds', $accounts);
        } else {
            $qb->innerJoin('f.accounts', 'a')
                ->andWhere('a.id IN (:accountsIds)')
                ->setParameter('accountsIds', $accounts)
                ->andWhere('f.accessLevel <= :accessLevel OR f.accessLevel IS NULL')
                ->setParameter('accessLevel', $accessLevel);
        }

        return $qb->getQuery()->getResult();
    }

    public function findForAccountAndModules(Accounts $account, $modules)
    {
        $accounts = [$account->getId()];

        if ($account->getAccountType() == AccountType::PARENT) {
            $childAccounts = $account->getChildrenAccounts();
            foreach ($childAccounts as $account) {
                $accounts[] = $account->getId();
            }
        }

        $qb = $this->createQueryBuilder('f')
            ->innerJoin('f.accounts', 'a')
            ->andWhere('a.id IN (:account)')
            ->andWhere('f.type = :formType')
            ->andWhere('f.module IN (:modules)')
            ->setParameter('account', $accounts)
            ->setParameter('formType', 'form')
            ->setParameter('modules', $modules)
            ->orderBy('f.id', 'DESC')
            ->getQuery();

        return $qb->getResult();
    }

    public function findTemplatesForAccountsAndAccessLevel(Accounts $account, $accessLevel)
    {
        $qb = $this->createQueryBuilder('f');

        if ($accessLevel == Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
            $qb = $qb->leftJoin('f.accounts', 'a')
                ->andWhere('(f.type = :template AND a.id = :accountId) OR (f.type = :template AND a.id IS NULL)')
                ->setParameter('template', 'template')
                ->setParameter('accountId', $account->getId())
                ->getQuery();

            return $qb->getResult();
        }

        $qb = $qb->innerJoin('f.accounts', 'a')
            ->andWhere('a.id = :accountId')
            ->andWhere('f.type = :form')
            ->setParameter('form', 'template')
            ->setParameter('accountId', $account->getId())
            ->getQuery();


        return $qb->getResult();
    }

    public function findWithSharedFieldsForAccount(Accounts $account)
    {
        $qb = $this->createQueryBuilder('f')
            ->innerJoin('f.accounts', 'a')
            ->andWhere('a.id = :accountId')
            ->andWhere('f.type = :form')
            ->andWhere('f.hasSharedFields = :hasSharedFields')
            ->setParameter('form', FormType::FORM)
            ->setParameter('accountId', $account->getId())
            ->setParameter('hasSharedFields', true)
            ->getQuery();

        return $qb->getResult();
    }


    private function stripSpacesFromSchema(string $schema)
    {
        $array = json_decode($schema, true);

        array_walk_recursive($array, function (&$v, $k) {
            if (is_string($v)) {
                $v = trim($v);
            }
        });

        return json_encode($array);
    }
}
