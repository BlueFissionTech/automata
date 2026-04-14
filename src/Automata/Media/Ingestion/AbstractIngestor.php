<?php

namespace BlueFission\Automata\Media\Ingestion;

use BlueFission\Automata\Context;
use BlueFission\Automata\InputTypeDetector;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Stream;
use BlueFission\Str;

abstract class AbstractIngestor implements IIngestor
{
    protected function resolveContext(array $options): Context
    {
        $context = $options['context'] ?? null;

        if ($context instanceof Context) {
            return $context;
        }

        $contextObj = new Context();
        if (is_array($context)) {
            foreach ($context as $key => $value) {
                $contextObj->set($key, $value);
            }
        }

        return $contextObj;
    }

    protected function resolveStream($input): ?Stream
    {
        if ($input instanceof Stream) {
            return $input;
        }

        if (is_resource($input)) {
            return new Stream($input);
        }

        if ($input instanceof \SplFileObject) {
            $path = $input->getRealPath();
            if ($path) {
                return $this->streamFromPath($path);
            }
        }

        return null;
    }

    protected function streamFromPath(string $path): ?Stream
    {
        $resource = @fopen($path, 'rb');
        if ($resource === false) {
            return null;
        }

        return new Stream($resource);
    }

    protected function detectMimeType($input): ?string
    {
        if ($input instanceof Stream && is_resource($input->resource())) {
            $meta = $input->meta();
            if (isset($meta['uri']) && is_string($meta['uri']) && is_file($meta['uri'])) {
                return $this->mimeFromPath($meta['uri']);
            }
        }

        if (Str::is($input) && is_file($input)) {
            return $this->mimeFromPath($input);
        }

        if (Str::is($input)) {
            return $this->mimeFromBuffer($input);
        }

        return null;
    }

    protected function mimeFromPath(string $path): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);
        return $mimeType ?: null;
    }

    protected function mimeFromBuffer(string $buffer): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($buffer);
        return $mimeType ?: null;
    }

    protected function readContent($input, array $options = []): string
    {
        $maxBytes = isset($options['max_bytes']) ? (int)$options['max_bytes'] : null;

        if ($input instanceof Stream) {
            $input->rewind();
            return $input->read($maxBytes);
        }

        if (is_resource($input)) {
            $stream = new Stream($input);
            $stream->rewind();
            return $stream->read($maxBytes);
        }

        if (Str::is($input) && is_file($input)) {
            if ($maxBytes !== null && $maxBytes > 0) {
                $handle = @fopen($input, 'rb');
                if ($handle) {
                    $data = fread($handle, $maxBytes);
                    fclose($handle);
                    return $data === false ? '' : $data;
                }
            }

            $data = @file_get_contents($input);
            return $data === false ? '' : $data;
        }

        if (Str::is($input)) {
            if ($maxBytes !== null && $maxBytes > 0) {
                return substr($input, 0, $maxBytes);
            }
            return $input;
        }

        return '';
    }

    protected function baseMeta($input, ?string $mimeType = null): array
    {
        $meta = [];

        if ($mimeType) {
            $meta['mime'] = $mimeType;
        }

        if (Str::is($input) && is_file($input)) {
            $meta['path'] = $input;
            $size = @filesize($input);
            if ($size !== false) {
                $meta['size'] = (int)$size;
            }
        }

        if ($input instanceof Stream) {
            $meta['stream'] = $input->meta();
            $length = $input->length();
            if ($length !== null) {
                $meta['size'] = $length;
            }
        }

        return $meta;
    }

    protected function buildItem(string $type, $content, array $meta, array $options = []): MediaItem
    {
        $context = $this->resolveContext($options);
        $item = new MediaItem($type, $content, $meta, $context);

        if (isset($meta['path']) && is_string($meta['path'])) {
            $item->path($meta['path']);
        }

        if (isset($options['stream']) && $options['stream'] instanceof Stream) {
            $item->stream($options['stream']);
        }

        return $item;
    }

    protected function resolveType($input, array $options = []): ?string
    {
        if (isset($options['force_type']) && is_string($options['force_type'])) {
            return $options['force_type'];
        }

        return InputTypeDetector::detect($input);
    }
}
