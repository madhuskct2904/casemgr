<?php


namespace App\Transformers;

/**
 * Class MessageStatusTransformer
 * @package App\Transformers
 */
class MessageStatusTransformer
{
    /**
     * @var array
     */
    private static $statuses = [
        'queued' => 'Successful',
        'error'  => 'Exception'
    ];

    /**
     * @param string $status
     *
     * @return string
     */
    public static function transform(string $status): string
    {
        if (array_key_exists($status, self::$statuses) === false) {
            return $status;
        }

        return self::$statuses[$status];
    }
}
