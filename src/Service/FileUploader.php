<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class FileUploader
 *
 * @package App\Service
 */
class FileUploader
{
    /** @var string */
    private $target_dir;

    /**
     * FileUploader constructor.
     *
     * @param string $target_dir
     */
    public function __construct($target_dir)
    {
        $this->target_dir = $target_dir;
    }

    /**
     * @param UploadedFile $file
     *
     * @return string
     */
    public function upload(UploadedFile $file): string
    {
        $fileName = md5(uniqid()).'.'.$file->guessExtension();

        $file->move($this->getTargetDir(), $fileName);

        return $fileName;
    }

    /**
     * @return string
     */
    public function getTargetDir(): string
    {
        return $this->target_dir;
    }
}
