<?php
declare(strict_types=1);

namespace App\Exception;

class AuthException extends ResponseException
{
    /**
     * @throws AuthException
     */
    public static function invalidTokenException(): self
    {
        throw new self("Invalid token.", 0, null, 401);
    }

    /**
     * @throws AuthException
     */
    public static function noAccess(): self
    {
        throw new self("No access.", 0, null, 403);
    }
}