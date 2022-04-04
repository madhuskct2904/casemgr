<?php
declare(strict_types=1);

namespace App\Exception;

use Exception;
use Throwable;

class ResponseException extends Exception
{
    private int $statusCode;

    public function __construct($message = "", $code = 0, Throwable $previous = null, int $statusCode = 400)
    {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}