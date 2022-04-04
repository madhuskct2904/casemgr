<?php
declare(strict_types=1);

namespace App\Exception;

class EntityException extends ResponseException
{
    /**
     * @throws EntityException
     */
    public static function notFound(): self
    {
        throw new self("Record not found.", 0, null, 404);
    }
}