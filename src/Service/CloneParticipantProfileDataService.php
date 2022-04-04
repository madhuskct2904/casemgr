<?php

namespace App\Service;

use App\Domain\FormValues\CloneParticipantProfileDataException;
use App\Entity\Accounts;
use App\Entity\FormsData;
use App\Entity\FormsValues;
use App\Entity\Users;
use App\Entity\UsersSettings;
use App\Enum\AccountType;
use App\Enum\ParticipantType;
use App\Event\FormCreatedEvent;
use App\Service\S3ClientFactory;
use App\Utils\Helper;
use Aws\S3\Exception\S3Exception;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class MessageCallbackService
 * @package App\Service
 */
class CloneParticipantProfileDataService
{
    /**
     * @var EntityManagerInterface
     */
    private $em;
    private $accountFormsService;
    private $eventDispatcher;
    private $s3Client;
    private $s3BucketName;
    private $awsFormsFolder;

    /**
     * MessageCallbackService constructor.
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(
        EntityManagerInterface $em,
        AccountFormsService $accountFormsService,
        EventDispatcherInterface $eventDispatcher,
        S3ClientFactory $s3ClientFactory,
        $s3BucketName,
        $awsFormsFolder
    )
    {
        $this->em = $em;
        $this->accountFormsService = $accountFormsService;
        $this->eventDispatcher = $eventDispatcher;
        $this->s3Client = $s3ClientFactory->getClient();
        $this->s3BucketName = $s3BucketName;
        $this->awsFormsFolder = $awsFormsFolder;
    }

    public function prepareClone(Accounts $accounts, Users $participant)
    {
        if ($accounts->getParticipantType() == ParticipantType::INDIVIDUAL) {
            $module = $this->em->getRepository('App:Modules')->findOneBy(['key' => 'participants_profile']);
        }

        if ($accounts->getParticipantType() == ParticipantType::MEMBER) {
            $module = $this->em->getRepository('App:Modules')->findOneBy(['key' => 'members_profile']);
        }

        if (!isset($module)) {
            throw new CloneParticipantProfileDataException('Profile form module not found');
        }

        if (in_array($accounts->getAccountType(), [AccountType::CHILD, AccountType::PARENT])) {
            $this->checkIfAccountIsInHierarchy($accounts, $participant);
            return $this->cloneForAccountsInHierarchy($participant, $module);
        }

        if (in_array($accounts->getAccountType(), [AccountType::PROGRAM, AccountType::DEFAULT])) {
            $this->checkIfAccountsIsInSearchInAccounts($accounts, $participant);
            return $this->cloneForProgramOrDefault($participant, $module, $accounts);
        }
    }

    public function cloneParticipant(Accounts $accounts, Users $participant)
    {
        if ($accounts->getParticipantType() == ParticipantType::INDIVIDUAL) {
            $profileModule = $this->em->getRepository('App:Modules')->findOneBy(['key' => 'participants_profile']);
            $userData = $this->em->getRepository('App:UsersData')->findOneBy(['user' => $participant]);
        }

        if ($accounts->getParticipantType() == ParticipantType::MEMBER) {
            $profileModule = $this->em->getRepository('App:Modules')->findOneBy(['key' => 'members_profile']);
            $userData = $this->em->getRepository('App:MemberData')->findOneBy(['user' => $participant]);
        }

        $contactFormModule = $this->em->getRepository('App:Modules')->findOneBy(['key' => 'participants_contact']);

        if (!isset($profileModule)) {
            throw new CloneParticipantProfileDataException('Profile form module not found');
        }

        $this->checkIfAccountIsInHierarchy($accounts, $participant);

        $username = strtolower(Helper::generateCode(4)) . uniqid();
        $email = sprintf('%s@casemgr.org', $username);

        $newParticipant = clone $participant;
        $newParticipant->setUsername($username);
        $newParticipant->setPlainPassword(Helper::generateCode(8, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz123456789'));
        $newParticipant->setEmail($email);

        $this->em->persist($newParticipant);
        $this->em->flush();

        $newParticipant->clearAccounts();
        $newParticipant->addAccount($accounts);
        $this->em->flush();

        $newUserData = clone $userData;
        $newUserData->setSystemId(strtolower(Helper::generateCode(9)));
        $newUserData->setStatus(null);
        $newUserData->setStatusLabel(null);
        $newUserData->setUser($newParticipant);
        $this->em->persist($newUserData);
        $this->em->flush();


        foreach ($participant->getSettings() as $setting) {
            $settings = new UsersSettings();
            $settings->setUser($newParticipant);
            $settings->setValue($setting->getValue());
            $settings->setName($setting->getName());
            $this->em->persist($settings);
            $this->em->flush();
        }

        $profileData = $this->em->getRepository('App:FormsData')->findOneBy([
            'module'     => $profileModule,
            'element_id' => $participant->getId(),
            'assignment' => null
        ], ['id' => 'DESC']);

        $this->cloneForm($accounts, $profileData, $newParticipant);

        $contactFormData = $this->em->getRepository('App:FormsData')->findOneBy([
            'module'     => $contactFormModule,
            'element_id' => $participant->getId(),
            'assignment' => null
        ], ['id' => 'DESC']);

        $this->cloneForm($accounts, $contactFormData, $newParticipant);

        return $newParticipant->getId();
    }

    /**
     * @param Accounts $accounts
     * @param Users $participant
     * @throws Exception
     */
    private function checkIfAccountIsInHierarchy(Accounts $accounts, Users $participant): void
    {
        $participantAccounts = $participant->getAccounts();
        $participantAccountsIds = [];

        foreach ($participantAccounts as $participantAccount) {
            $participantAccountsIds[] = $participantAccount->getId();
        }

        $relatedAccountsIds = [$accounts->getId()];

        if ($accounts->getAccountType() == AccountType::CHILD) {
            $relatedAccountsIds[] = $accounts->getParentAccount()->getId();
            foreach ($accounts->getParentAccount()->getChildrenAccounts() as $relatedAccount) {
                $relatedAccountsIds[] = $relatedAccount->getId();
            }
        }

        if ($accounts->getAccountType() == AccountType::PARENT) {
            foreach ($accounts->getChildrenAccounts() as $relatedAccount) {
                $relatedAccountsIds[] = $relatedAccount->getId();
            }
        }

        if (empty(array_intersect(array_unique($participantAccountsIds), array_unique($relatedAccountsIds)))) {
            throw new Exception('Security violation.');
        }
    }


    private function checkIfAccountsIsInSearchInAccounts(Accounts $account, Users $participant): void
    {
        $participantsAccounts = $participant->getAccounts();
        $relatedAccounts = json_decode($account->getSearchInOrganizations(), true);
        $relatedAccountsIds = array_column($relatedAccounts, 'id');
        $participantsAccountsIds = [];

        foreach ($participantsAccounts as $participantsAccount) {
            $participantsAccountsIds[] = $participantsAccount->getId();
        }

        if (empty(array_intersect(array_unique($participantsAccountsIds), array_unique($relatedAccountsIds)))) {
            throw new Exception('Security violation.');
        }
    }

    /**
     * @param Accounts $accounts
     * @param FormsData|null $formData
     * @param Users $newParticipant
     */
    protected function cloneForm(Accounts $accounts, ?FormsData $formData, Users $newParticipant): void
    {
        if (!$formData) {
            return;
        }

        $newFormData = clone $formData;
        $newFormData->setAccount($accounts);
        $newFormData->setElementId($newParticipant->getId());

        $this->em->persist($newFormData);
        $this->em->flush();

        if ($formData !== null) {
            $formValues = $this->em->getRepository('App:FormsValues')->findByData($formData);

            foreach ($formValues as $value) {
                $newFormValue = new FormsValues();

                $newName = $value->getName();
                $newValue = $value->getValue();

                if (strpos($newName, 'file-') === 0) {
                    $oldValues = json_decode($newValue, true);
                    $newValue = [];

                    if (!is_array($oldValues)) {
                        continue;
                    }

                    foreach ($oldValues as $oldValue) {
                        if (!isset($oldValue['file'])) {
                            continue;
                        }

                        try {
                            $sourceFilename = $oldValue['file'];
                            $newValue[] = [
                                'name' => $oldValue['name'],
                                'file' => $this->copyFileS3($sourceFilename, $newParticipant->getId())
                            ];
                        } catch (Exception $e) {
                            continue;
                        }
                    }

                    $newValue = json_encode($newValue);
                }

                if (strpos($newName, 'image-upload-') === 0) {
                    try {
                        $newValue = $this->copyFileS3($newValue, $newParticipant->getId());
                    } catch (Exception $e) {
                        continue;
                    }
                }

                $newFormValue->setData($newFormData);
                $newFormValue->setName($newName);
                $newFormValue->setValue($newValue);

                $newFormValue->setDate($value->getDate());
                $this->em->persist($newFormValue);
                $this->em->flush();
            }
        }

        $this->eventDispatcher->dispatch(new FormCreatedEvent($newFormData), FormCreatedEvent::class);
    }

    protected function copyFileS3(string $fileName, int $elementId): string
    {
        $client = $this->s3Client;
        $bucket = $this->s3BucketName;
        $prefix = $this->awsFormsFolder;
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        $newFileName = md5(time() . $elementId) . '.' . $extension;

        try {
            $client->copyObject([
                'Bucket'     => $bucket,
                'Key'        => $prefix . '/' . $newFileName,
                'CopySource' => $bucket . '/' . $prefix . '/' . $fileName,
                'ACL'        => 'public-read'
            ]);
        } catch (S3Exception $e) {
            throw new Exception('Something went wrong while copying file in S3 bucket.');
        }

        return $newFileName;
    }

    /**
     * @param Users $participant
     * @param \App\Entity\Modules|null $module
     * @return array
     */
    protected function cloneForAccountsInHierarchy(Users $participant, ?\App\Entity\Modules $module): array
    {
        $formData = $this->em->getRepository('App:FormsData')->findOneBy([
            'module'     => $module,
            'element_id' => $participant->getId(),
            'assignment' => null
        ], ['id' => 'DESC']);

        $values = [];

        $newParticipantId = $this->em->getRepository('App:Users')->findMaxUserId() + 1;

        if (!$formData) {
            return $values;
        }

        $formValues = $this->em->getRepository('App:FormsValues')->findByData($formData);

        foreach ($formValues as $value) {
            $newName = $value->getName();
            $newValue = $value->getValue();

            if (strpos($newName, 'file-') === 0) {
                $oldValues = json_decode($newValue, true);
                $newValue = [];

                if (!is_array($oldValues)) {
                    continue;
                }

                foreach ($oldValues as $oldValue) {
                    if (!isset($oldValue['file'])) {
                        continue;
                    }

                    try {
                        $sourceFilename = $oldValue['file'];
                        $newValue[] = [
                            'name' => $oldValue['name'],
                            'file' => $this->copyFileS3($sourceFilename, $newParticipantId)
                        ];
                    } catch (Exception $e) {
                        continue;
                    }
                }

                $newValue = json_encode($newValue);
            }

            if (strpos($newName, 'image-upload-') === 0) {
                try {
                    $newValue = $this->copyFileS3($newValue, $newParticipantId);
                } catch (Exception $e) {
                    continue;
                }
            }

            $values[$value->getName()] = $newValue;
        }

        return $values;
    }


    protected function cloneForProgramOrDefault(Users $participant, ?\App\Entity\Modules $module, Accounts $accounts): array
    {
        $formData = $this->em->getRepository('App:FormsData')->findOneBy([
            'module'     => $module,
            'element_id' => $participant->getId(),
            'assignment' => null
        ], ['id' => 'DESC']);

        $values = [];

        if (!$formData) {
            return $values;
        }

        $formValues = $this->em->getRepository('App:FormsValues')->findByData($formData);

        $form = $formData->getForm();
        $sourceColumnsMap = json_decode($form->getColumnsMap(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $values;
        }

        $this->accountFormsService->setAccount($accounts);
        $destinationAccountProfileForm = $this->accountFormsService->getProfileForm();

        $destinationColumnsMap = json_decode($destinationAccountProfileForm->getColumnsMap(), true);


        if (json_last_error() !== JSON_ERROR_NONE) {
            return $values;
        }

        $dstMap = [];
        $fieldsMap = [];

//        map destination columns map to assoc array
        foreach ($destinationColumnsMap as $item) {
            $dstMap[$item['name']] = $item['value'];
        }

//        map old map as oldFieldName = newFieldName
        foreach ($sourceColumnsMap as $item) {
            if (isset($dstMap[$item['name']])) {
                $fieldsMap[$item['value']] = $dstMap[$item['name']];
            }
        }

        foreach ($formValues as $value) {
            $fieldName = $value->getName();
            $fieldValue = $value->getValue();

            if (isset($fieldsMap[$fieldName])) {
                $values[$fieldsMap[$fieldName]] = $fieldValue;
            }
        }

        return $values;
    }
}
