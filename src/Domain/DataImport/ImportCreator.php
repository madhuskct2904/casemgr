<?php

namespace App\Domain\DataImport;

use App\Entity\Accounts;
use App\Entity\Imports;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;

class ImportCreator
{
    private $em;
    private $importFileService;

    public function __construct(EntityManagerInterface $em, ImportFileService $importFileService)
    {
        $this->em = $em;
        $this->importFileService = $importFileService;
    }

    public function create(Accounts $account, Users $user, int $formId, string $filename, string $originalFilename, array $map, int $totalRows, array $ignoreRows, array $keyField): Imports
    {
        $import = new Imports();

        $import->setTotalRows($totalRows);

        $csvHeader = $this->importFileService->getCsvFromBucket($filename, 0, 1)[0];

        $import->setMap($map);
        $import->setCsvHeader($csvHeader);
        $import->setAccount($account);
        $import->setCreatedDate(new \DateTime());
        $import->setUser($user);
        $import->setFile($filename);
        $import->setOriginalFile($originalFilename);
        $import->setIgnoreRows($ignoreRows);

        if (!$keyField) {
            throw new ImportCreatorException('Key field must be set!');
        }

        $import->setKeyField($keyField);

        if ($formId !== 0) {
            $form = $this->em->getRepository('App:Forms')->find($formId);

            if (!$form) {
                throw new ImportCreatorException('Invalid form ID!');
            }
            $import->setForm($form);
            $import->setFormAccount($account);
            $import->setContext(ImportContext::FORM);
        } else {
            $import->setContext(ImportContext::COMMUNICATION_NOTE);
        }

        $this->em->persist($import);
        $this->em->flush();

        return $import;
    }
}
