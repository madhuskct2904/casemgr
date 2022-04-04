<?php
declare(strict_types=1);

namespace App\Transformers;

class ReportsTransformer
{
    public const CREATOR_ID = '%s at %s';
    public const EDITOR_ID = '%s at %s';

    public static function creatorId(string $userName, string $createdAt): string
    {
        return sprintf(self::CREATOR_ID, $userName, $createdAt);
    }

    public static function editorId(string $userName, string $createdAt, string $editedAt): ?string
    {
        if ($createdAt === $editedAt) {
            return null;
        }

        return sprintf(self::EDITOR_ID, $userName, $editedAt);
    }

    public static function parseDate(string $datetime, string $timezone, array $timezoneInfo): string
    {
        $datetime = new \DateTime($datetime);
        $userFormat = $timezoneInfo['phpDateFormat'];
        $format = $userFormat . ' h:i A';

        try {
            $datetime->setTimezone(new \DateTimeZone($timezone));
        } catch (\Exception $e) {
            // ...
        }

        return $datetime->format($format);
    }
}