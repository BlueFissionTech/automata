<?php

namespace BlueFission\Automata;

use BlueFission\Str;

class InputTypeDetector
{
    public static function detect($input): ?string
    {
        if (self::isUrl($input)) {
            return InputType::URL;
        }

        if (self::isImage($input)) {
            return InputType::IMAGE;
        }

        if (self::isAudio($input)) {
            return InputType::AUDIO;
        }

        if (self::isVideo($input)) {
            return InputType::VIDEO;
        }

        if (self::isDocument($input)) {
            return InputType::DOCUMENT;
        }

        if (Str::is($input)) {
            return InputType::TEXT;
        }

        return null;
    }

    private static function isImage($input): bool
    {
        $mimeType = self::detectMimeType($input);
        if (!$mimeType) {
            return false;
        }

        return (Str::pos($mimeType, 'image/') === 0);
    }

    private static function isAudio($input): bool
    {
        $mimeType = self::detectMimeType($input);
        if (!$mimeType) {
            return false;
        }

        return (Str::pos($mimeType, 'audio/') === 0);
    }

    private static function isVideo($input): bool
    {
        $mimeType = self::detectMimeType($input);
        if (!$mimeType) {
            return false;
        }

        return (Str::pos($mimeType, 'video/') === 0);
    }

    private static function isDocument($input): bool
    {
        $mimeType = self::detectMimeType($input);
        if (!$mimeType) {
            return false;
        }

        return $mimeType === 'application/pdf';
    }

    private static function isUrl($input): bool
    {
        if (!Str::is($input)) {
            return false;
        }

        return (bool)preg_match('#^https?://#i', $input);
    }

    private static function detectMimeType($input): ?string
    {
        if (!Str::is($input)) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        if (is_file($input)) {
            $mimeType = $finfo->file($input);
            return $mimeType ?: null;
        }

        $mimeType = $finfo->buffer($input);
        return $mimeType ?: null;
    }
}
