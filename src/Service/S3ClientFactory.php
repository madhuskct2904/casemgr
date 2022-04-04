<?php

namespace App\Service;

use Aws\S3\S3Client;

class S3ClientFactory
{
    private S3Client $s3Client;

    public function __construct(string $key, string $secret, string $region, string $version)
    {
        $this->s3Client = new S3Client([
            'credentials' => [
                'key' => $key,
                'secret' => $secret
            ],
            'region' => $region,
            'version' => $version
        ]);
    }

    public function getClient()
    {
        return $this->s3Client;
    }
}