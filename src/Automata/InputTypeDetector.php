<?php

namespace BlueFission\Automata;

use BlueFission\Str;

class InputTypeDetector
{
    public static function detect($input): ?string
    {
        if (self::isImage($input)) {
            return InputType::IMAGE;
        }

        if (self::isVideo($input)) {
            return InputType::VIDEO;
        }

        if (Str::is($input)) {
            return InputType::TEXT;
        }

        return null;
    }

    private static function isImage($input): bool
    {
        if (!Str::is($input)) {
            return false;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($input);

        return (Str::pos($mimeType, 'image/') === 0);
    }

    private static function isVideo($input): bool
    {
        if (!Str::is($input)) {
            return false;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($input);

        return (Str::pos($mimeType, 'video/') === 0);
    }
}
