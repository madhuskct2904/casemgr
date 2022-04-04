<?php

namespace App\Domain\Form;

use App\Entity\Forms;
use Doctrine\ORM\EntityManagerInterface;

class ProgramsService
{

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function updateFormPrograms(Forms $form, array $formPrograms): Forms
    {
        $oldPrograms = $form->getPrograms();

        foreach ($oldPrograms as $program) {
            $program->removeForm($form);
        }

        foreach ($formPrograms as $formProgram) {
            if ($program = $this->em->getRepository('App:Programs')->find($formProgram['id'])) {
                $program->addForm($form);
            }
        }

        $this->em->flush();
        $this->em->refresh($form);

        return $form;
    }
}
