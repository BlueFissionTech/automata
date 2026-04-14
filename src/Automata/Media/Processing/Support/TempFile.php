<?php

namespace BlueFission\Automata\Media\Processing\Support;

class TempFile
{
    public static function createFromContent(string $content, string $extension = ''): ?string
    {
        $extension = ltrim($extension, '.');
        $path = tempnam(sys_get_temp_dir(), 'automata_media_');

        if ($path === false) {
            return null;
        }

        if ($extension !== '') {
            $newPath = $path . '.' . $extension;
            if (!@rename($path, $newPath)) {
                @unlink($path);
                return null;
            }
            $path = $newPath;
        }

        if ($content !== '') {
            @file_put_contents($path, $content);
        }

        return $path;
    }

    public static function cleanup(?string $path): void
    {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }
}
