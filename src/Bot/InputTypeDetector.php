<?php

namespace BlueFission\Bot;

class InputTypeDetector
{
    public static function detect($input): ?string
    {
        if (is_string($input)) {
            return DataType::TEXT;
        }

        if (self::isImage($input)) {
            return DataType::IMAGE;
        }

        if (self::isVideo($input)) {
            return DataType::VIDEO;
        }

        return null;
    }

    private static function isImage($input): bool
    {
        if (!is_string($input)) {
            return false;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($input);

        return (strpos($mimeType, 'image/') === 0);
    }

    private static function isVideo($input): bool
    {
        if (!is_string($input)) {
            return false;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($input);

        return (strpos($mimeType, 'video/') === 0);
    }
}
