<?php

namespace App\Service\Forms;

use App\Domain\Form\SharedFieldsService;
use App\Entity\Accounts;
use App\Entity\Forms;
use App\Entity\FormsData;
use App\Entity\FormsValues;
use App\Entity\Users;
use App\Service\S3ClientFactory;
use Aws\S3\Exception\S3Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SaveValuesService
{
    private $em;
    private $s3Client;
    private $s3BucketName;
    private $awsFormsFolder;
    private $projectDir;
    private $sharedFieldsService;

    public function __construct(
        EntityManagerInterface $em,
        S3ClientFactory $s3ClientFactory,
        string $s3BucketName,
        string $awsFormsFolder,
        string $projectDir,
        SharedFieldsService $sharedFieldsService
    )
    {
        $this->em = $em;
        $this->s3Client = $s3ClientFactory->getClient();
        $this->s3BucketName = $s3BucketName;
        $this->awsFormsFolder = $awsFormsFolder;
        $this->projectDir = $projectDir;
        $this->sharedFieldsService = $sharedFieldsService;
    }

    public function createFormData(Forms $form, Accounts $account, ?Users $user, ?Users $participant): FormsData
    {
        $formData = new FormsData();
        $formData->setAccount($account);
        $formData->setModule($form->getModule());
        $formData->setForm($form);
        $formData->setElementId($participant ? $participant->getId() : 0);
        $formData->setCreatedDate(new \DateTime());
        $formData->setUpdatedDate(new \DateTime());

        if ($user) {
            $formData->setEditor($user);
            $formData->setCreator($user);
        }

        if ($participant && $participant->getData()->getCaseManager()) {
            $formData->setManager($this->em->getRepository('App:Users')->find($participant->getData()->getCaseManager()));
        }

        if ($participant && $participant->getData()->getCaseManagerSecondary()) {
            $formData->setSecondaryManager($this->em->getRepository('App:Users')->find($participant->getData()->getCaseManagerSecondary()));
        }

        $this->em->persist($formData);
        $this->em->flush();

        return $formData;
    }

    public function clearValues(FormsData $formData, array $fields)
    {
        $formValues = $this->em->getRepository('App:FormsValues')->findByNamesInData($fields, $formData);
        foreach ($formValues as $formValue) {
            $this->em->remove($formValue);
        }

        $this->em->flush();
    }

    public function clearAllValues(FormsData $formsData): void
    {
        $values = $formsData->getValues();
        foreach ($values as $value) {
            $this->em->remove($value);
        }
        $this->em->flush();
    }

    public function storeValues(FormsData $formData, array $data, array $files, ?Users $user = null, ?Accounts $account = null)
    {
        $formData->setUpdatedDate(new \DateTime());

        if ($user) {
            $formData->setEditor($user);
        }

        if ($account) {
            $formData->setAccount($account);
        }

        $this->em->persist($formData);
        $this->em->flush();

        $data = $this->storeFiles($files, $data, $formData);

        foreach ($data as $row) {
            $formValue = new FormsValues();
            $formValue->setData($formData);

            if (!isset($row['name'], $row['value'])) {
                continue;
            }
            if (strpos($row['name'], 'shared-field') === 0) {
                $sharedField     = $this->em->getRepository('App:SharedField')->findOneBy(['fieldName' => $row['name'], 'form' => $formData->getForm()]);
                $sharedFormValue = null;

                if ($sharedField && $sharedField->getSourceFormData() == 'last' && !$sharedField->isReadOnly()) {
                    $sharedFormData = $this->em->getRepository('App:FormsData')
                        ->findOneBy(
                            [
                                'form'       => $sharedField->getSourceForm(),
                                'element_id' => $formData->getElementId()
                            ],
                            [
                                'created_date' => 'DESC',
                            ]
                        )
                    ;

                    $sharedFormValue = $this->em->getRepository('App:FormsValues')->findOneBy(['data' => $sharedFormData, 'name' => $sharedField->getSourceFieldName()]);
                }

                if (null !== $sharedFormValue) {
                    $sharedFormValue->setValue($row['value']);
                    $this->em->flush();
                }
            }

            if (
                (strpos($row['name'], 'signature-') === false) &&
                strpos($row['name'], 'shared-field') !== 0 && // fix - in profile form on form update shared field with signature data was changed from data:image to filename.png
                substr($row['value'], 0, 22) === 'data:image/png;base64,'
            ) {
                $img = str_replace('data:image/png;base64,', '', $row['value']);
                $fileName = sprintf('%s.png', md5(time() . $formData->getId()));
                $decodedImage = base64_decode($img);

                if ((base64_encode(base64_decode($img, true))) === $img) {

                    try {
                        $this->s3Client->putObject([
                            'Bucket' => $this->s3BucketName,
                            'Key'    => $this->awsFormsFolder . '/' . $fileName,
                            'Body'   => $decodedImage,
                            'ACL'    => 'public-read'
                        ]);
                    } catch (S3Exception $e) {

                    }

                    $row['value'] = $fileName;
                }
            }

            if ((strpos($row['name'], 'text-') !== false) || strpos($row['name'], 'textarea-') !== false) {
                $row['value'] = trim($row['value']);
            }

            $formValue->setName($row['name']);
            $formValue->setValue($row['value']);
            $formValue->setDate(new \DateTime());

            $this->em->persist($formValue);
        }

        $this->em->flush();
        $this->em->refresh($formData);
    }

    /**
     * @param array $files
     * @param array $data
     * @param FormsData $formData
     * @return array
     */
    private function storeFiles(array $files, array $data, FormsData $formData): array
    {
        $filesInFiled = [];

        foreach ($files as $fieldIdx => $file) {
            $explode = explode('-', $fieldIdx);

            unset($explode[count($explode) - 1]);

            $fieldIdx = implode('-', $explode);

            $fieldName = array_search($fieldIdx, array_column($data, 'name'));

            if ($fieldName === false) {
                continue;
            }

            $uploaded = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['size']);
            $fileName = sprintf(
                '%s.%s',
                md5(time() . $fieldIdx . $formData->getId() . $file['name'] . mt_rand()),
                $uploaded->guessExtension()
            );

            try {
                $this->s3Client->putObject([
                    'Bucket'     => $this->s3BucketName,
                    'Key'        => $this->awsFormsFolder . '/' . $fileName,
                    'SourceFile' => $file['tmp_name'],
                    //'ACL'           => 'public-read'
                ]);
            } catch (S3Exception $e) {
//            todo: response with error
            }

            $filesInFiled[$fieldName][] = [
                'name' => $file['name'],
                'file' => $fileName
            ];
        }

        foreach ($filesInFiled as $fieldName => $fieldValue) {
            if (!empty($data[$fieldName]['value'])) {
                $previousData = json_decode($data[$fieldName]['value'], true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($previousData)) {
                    $newData = array_merge($previousData, $fieldValue);
                } else {
                    $newData = $fieldValue;
                }
            } else {
                $newData = $fieldValue;
            }

            $newData = array_values($newData);

            $data[$fieldName]['value'] = json_encode($newData);
        }

        return $data;
    }
}
