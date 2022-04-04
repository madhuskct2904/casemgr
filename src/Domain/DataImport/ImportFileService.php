<?php


namespace App\Domain\DataImport;

use App\Service\S3ClientFactory;
use Aws\S3\Exception\S3Exception;

class ImportFileService
{
    private $s3Client;
    private $s3BucketName;
    private $awsImportsFolder;

    public function __construct(S3ClientFactory $s3ClientFactory, string $s3BucketName, string $awsImportsFolder)
    {
        $this->s3Client = $s3ClientFactory->getClient();
        $this->s3BucketName = $s3BucketName;
        $this->awsImportsFolder = $awsImportsFolder;
    }

    public function upload($file, string $filename)
    {
        try {
            $this->s3Client->putObject([
                'Bucket'     => $this->s3BucketName,
                'Key'        => $this->awsImportsFolder . '/' . $filename,
                'SourceFile' => $file['tmp_name']
            ]);
        } catch (S3Exception $e) {
            throw new ImportFileServiceException('Upload error.');
        }
    }

    public function deleteFile($filename): void
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->s3BucketName,
                'Key'    => $this->awsImportsFolder . '/' . $filename
            ]);
        } catch (S3Exception $e) {
            throw new ImportFileServiceException('File delete error.');
        }
    }

    public function countFileLines($filename): int
    {
        return count($this->getCsvFromBucket($filename));
    }

    public function getCsvFromBucket($filename, $offset = 0, $limit = false): array
    {
        $csv = [];
        $client = $this->s3Client;

        try {
            $client->registerStreamWrapper();
            $url = "s3://$this->s3BucketName/$this->awsImportsFolder/$filename";
            $file = new \SplFileObject($url, 'r');

            $i = -1;

            while (!$file->eof()) {
                $i++;

                $bom = pack('H*', 'EFBBBF');
                $line = $file->fgetcsv();
                $line = preg_replace("/^$bom/", '', $line);

                if ($i < $offset) {
                    continue;
                }

                $csv[$i] = array_map('utf8_encode', $line);

                if ($limit && $i >= $offset + $limit) {
                    return $csv;
                }
            }

        } catch (S3Exception $e) {
            throw new ImportManagerException('Can \'t read file from S3 bucket.');
        }

        return $csv;
    }

}
