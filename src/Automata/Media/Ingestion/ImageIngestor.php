<?php

namespace BlueFission\Automata\Media\Ingestion;

use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\MediaItem;

class ImageIngestor extends BinaryIngestor
{
    protected string $type = InputType::IMAGE;
    protected string $mimePrefix = 'image/';

    public function ingest($input, array $options = []): MediaItem
    {
        $item = parent::ingest($input, $options);
        $meta = $item->meta();
        $content = $item->content();

        $dimensions = $this->resolveDimensions($input, $content, $meta);
        if ($dimensions !== null) {
            $meta['width'] = $dimensions['width'];
            $meta['height'] = $dimensions['height'];
        }

        $item->meta($meta);
        return $item;
    }

    protected function resolveDimensions($input, $content, array $meta): ?array
    {
        if (function_exists('getimagesize')) {
            if (isset($meta['path']) && is_file($meta['path'])) {
                $data = @getimagesize($meta['path']);
                if (is_array($data)) {
                    return ['width' => (int)$data[0], 'height' => (int)$data[1]];
                }
            }
        }

        if (function_exists('getimagesizefromstring') && is_string($content) && $content !== '') {
            $data = @getimagesizefromstring($content);
            if (is_array($data)) {
                return ['width' => (int)$data[0], 'height' => (int)$data[1]];
            }
        }

        return null;
    }
}
