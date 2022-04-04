<?php


namespace App\Exception;

use Exception;
use Throwable;

/**
 * Class MessageServiceException
 * @package App\Exception
 */
class MessageServiceException extends Exception
{
    /**
     * @var array
     */
    protected $deleteStrings = [
        '[HTTP 400] Unable to create record:'
    ];

    /**
     * MessageServiceException constructor.
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $message = $this->parseMessage($message);

        parent::__construct($message, $code, $previous);
    }

    /**
     * @param string $message
     *
     * @return string
     */
    protected function parseMessage($message = ""): string
    {
        foreach ($this->deleteStrings as $string) {
            $message = str_replace($string, "", $message);
        }

        return trim($message);
    }
}
