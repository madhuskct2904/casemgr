<?php

namespace App\Domain\DataImport;

use App\Domain\DataImport\ImportFormHandler;
use App\Domain\Participant\ParticipantManager;
use App\Entity\Assignments;
use App\Entity\FormsData;
use App\Entity\Users;
use App\Enum\ParticipantStatus;
use App\Event\FormValuesCompletedEvent;
use App\Event\FormCreatedEvent;
use App\Service\AccountService;
use App\Service\FormCalculations;
use App\Service\Forms\SaveValuesService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ImportWorkerFormHandler extends BaseImportWorkerStrategy
{
    const SKIP_ACTIVITY_FEED = true;
    const FORCE_IMPORT = true;

    private $participantUser = null;
    private $participantUserId = null;
    private AccountService $accountService;
    private ContainerInterface $container;
    private EventDispatcherInterface $eventDispatcher;
    private FormCalculations $formCalculationsService;
    protected $importHandler;
    private EntityManagerInterface $doctrine;
    private ParticipantManager $participantManager;
    private SaveValuesService $saveValuesService;

    public function __construct(
        AccountService $accountService,
        ContainerInterface $container, // for forms handlers, should be removed in the future
        EntityManagerInterface $em,
        EventDispatcherInterface $eventDispatcher,
        FormCalculations $formCalculationsService,
        ImportFormHandler $importHandler,
        EntityManagerInterface $doctrine,
        ParticipantManager $participantManager,
        SaveValuesService $saveValuesService
    )
    {
        $this->accountService = $accountService;
        $this->container = $container;
        $this->doctrine = $doctrine;
        $this->em = $em;
        $this->eventDispatcher = $eventDispatcher;
        $this->formCalculationsService = $formCalculationsService;
        $this->importHandler = $importHandler;
        $this->participantManager = $participantManager;
        $this->saveValuesService = $saveValuesService;
    }

    public function getImportHandler(): ImportHandlerInterface
    {
        return $this->importHandler;
    }

    public function importCsvRow(array $csvRow): array
    {
        $row = $this->parseCsvRow($csvRow);
        $this->participantUserId = null;
        $this->participantUser = null;
        $keyFieldValue = $this->getKeyFieldValue($csvRow);

        if ($keyFieldValue && $this->getImportHandler()->getForm()->getModule()->getRole() !== 'profile') {
            $keyField = $this->getImportHandler()->getImportKeyField();
            $this->participantUserId = $this->findParticipantIdByKeyField($keyField['formId'], $keyField['fieldInForm'], $keyFieldValue);
            $this->participantUser = $this->em->getRepository('App:Users')->find($this->participantUserId);
        }

        $data = $this->prepareRowData($row);

        $saveValuesService = $this->saveValuesService;

        $form = $this->getImportHandler()->getForm();

        $output = [];

        $moduleName = $form->getModule()->getKey();
        $handler = null;
        $handlerClass = sprintf(
            'App\\Handler\\Modules\\%sHandler',
            preg_replace_callback('/(?:^|_)(.?)/', static function ($str) {
                return str_replace('_', '', strtoupper($str[0]));
            }, $moduleName)
        );

        $formDataId = $this->getDataId();

        if (class_exists($handlerClass)) {
            $handler = $this->container->get($handlerClass);

            $handler->setElementId($this->participantUserId);
            $handler->setForm($this->getImportHandler()->getForm());
            $handler->set($data);
            $handler->setForce(self::FORCE_IMPORT);
            $handler->setContainer($this->container);
            $handler->setDataId($formDataId);
            $handler->setAccount($this->getAccount());

            $error = $handler->validate();

            if ($error !== null) {
                $msg = is_array($error) ? json_encode($error) : $error;
                throw new ImportWorkerException($msg);
            }

            // Before action
            $handler->before(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'], $this->getAccount());
            $output = $handler->output();

            if (isset($output['id'])) {
                $participantUserId = $output['id'];
                $handler->setElementId($participantUserId);
                $this->participantUserId = $participantUserId;
                $this->participantUser = $this->em->getRepository('App:Users')->find($participantUserId);
            }
        }

        $assignment = $this->getAssignment();

        if (in_array($moduleName, [
                'activities_services',
                'assessment_outcomes',
                'organization_general',
                'organization'
            ]) && !$form->getMultipleEntries()) {
            $formData = $this->em->getRepository('App:FormsData')->findBy([
                'form'       => $this->getImportHandler()->getForm(),
                'element_id' => $this->participantUserId,
                'assignment' => $assignment
            ]);

            if (count($formData)) {
                throw new ImportWorkerException('Multiple entries for this form are not allowed!');
            }
        }

        if ($formDataId) {
            $formData = $this->em->getRepository('App:FormsData')->find($formDataId);
        } else {
            $formData = $saveValuesService->createFormData($form, $this->getAccount(), $this->getUser(), $this->participantUser);
        }

        $formData->setAssignment($assignment);
        $formData->setElementId($this->participantUserId ?: 0);

        if ($completedDate = $this->getCompletedAt($row)) {
            $formData->setCreatedDate($completedDate);
        }

        $this->em->persist($formData);
        $this->em->flush();

        $this->assignCaseManagers($formData);

        $systemValues = null;

        if ($handler !== null) {
            $systemValues = $handler->systemValues();
        }

        if (is_iterable($systemValues) && count($systemValues)) {
            foreach ($systemValues as $formFieldName => $value) {
                $data[] = ['name' => $formFieldName, 'value' => $value];
            }
        }

        $saveValuesService->storeValues($formData, $data, []);

        if ($handler !== null) {
            $handler->setDataId($formData->getId());
            $handler->after();
            $output += $handler->output();
        }

        if (!self::SKIP_ACTIVITY_FEED && in_array($moduleName, ['activities_services', 'assessment_outcomes'])) {
            $eventData = [
                'participant_id' => $this->participantUserId,
                'template'       => $moduleName,
                'title'          => $form->getName(),
                'template_id'    => $formData->getId(),
                'details'        => ['action' => 'created']
            ];

            $this->eventDispatcher->dispatch(new FormValuesCompletedEvent($eventData), FormValuesCompletedEvent::class);
        }

        $this->eventDispatcher->dispatch(new FormCreatedEvent($formData), FormCreatedEvent::class);

        if (!isset($this->output['message'])) {
            $output['message'] = 'Form created!';
        }

        return $output;
    }

    private function getAssignment(): ?Assignments
    {
        if ($this->participantUser->getData()->getStatus() === ParticipantStatus::ACTIVE) {
            return null;
        }

        return $this->em->getRepository('App:Assignments')->findLatestAssignmentForParticipant($this->participantUser);
    }

    protected function prepareRowData(array $row): array
    {
        $data = [];
        $formAccount = $this->account;

        foreach ($row as $name => $value) {
            if (strpos($name, 'checkbox-group-') === 0) {
                $values = explode(';', $value);

                foreach ($values as $k => $v) {
                    $data[] = [
                        'name'  => $name . '-' . $k,
                        'value' => $v
                    ];
                }

                continue;
            }

            if (strpos($name, 'programs-checkbox-group-') === 0) {
                $values = explode(';', $value);

                foreach ($values as $k => $v) {
                    $program = $this->em->getRepository('App:Programs')->findOneBy([
                        'name'    => $v,
                        'account' => $formAccount
                    ]);

                    if ($program) {
                        $data[] = [
                            'name'  => $name . '-' . $k,
                            'value' => $program->getId()
                        ];
                    }
                }

                continue;
            }

            $data[] = [
                'name'  => $name,
                'value' => $value
            ];
        }

        return $data;
    }


    private function assignCaseManagers(FormsData $formData): void
    {
        if ($pData = $this->participantUser->getData()) {
            $manager = $pData->getCaseManager() ? $this->em->getRepository('App:Users')->find($pData->getCaseManager()) : null;
            $manager2 = $pData->getCaseManagerSecondary() ? $this->em->getRepository('App:Users')->find($pData->getCaseManagerSecondary()) : null;
        }

        $formData->setManager($manager ?? null);
        $formData->setSecondaryManager($manager2 ?? null);

        $this->em->persist($formData);
        $this->em->flush();
    }

    private function getDataId(): ?int
    {
        if ($this->getImportHandler()->getForm()->getModule()->getRole() !== 'assignment') {
            return null;
        }

        if ($this->getAssignment() !== null) {
            return null;
        }

        $formData = $this->em->getRepository('App:FormsData')->findOneBy([
            'element_id' => $this->participantUserId,
            'module'     => $this->getImportHandler()->getForm()->getModule(),
            'assignment' => null
        ], ['id' => 'DESC']);

        if ($formData) {
            return $formData->getId();
        }

        return null;
    }

}
