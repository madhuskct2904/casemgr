<?php

namespace App\Domain\FormBuilder;

use App\Entity\Accounts;
use App\Domain\Form\FormSchemaHelper;
use Doctrine\ORM\EntityManagerInterface;

class FormBuilderHelper
{
    private $em;
    private $formHelper;
    private $account;

    public function __construct(EntityManagerInterface $em, FormSchemaHelper $formHelper)
    {
        $this->em = $em;
        $this->formHelper = $formHelper;
    }

    public function getAccount(): Accounts
    {
        return $this->account;
    }

    public function setAccount(Accounts $account): void
    {
        $this->account = $account;
    }

    public function getFormsWithSharedFields()
    {
        $forms = $this->em->getRepository('App:Forms')->findWithSharedFieldsForAccount($this->getAccount());
        $rows = [];

        foreach ($forms as $idx => $form) {
            $helper = $this->formHelper->setForm($form);
            $columns = $helper->getFlattenColumns();
            $rows[$idx] = [
                'id'            => $form->getId(),
                'name'          => $form->getName(),
                'sharedFields' => []
            ];

            foreach ($columns as $column) {
                if (isset($column['isShared']) && $column['isShared']) {
                    $rows[$idx]['sharedFields'][] = $column;
                }
            }
        }

        return $rows;
    }
}
