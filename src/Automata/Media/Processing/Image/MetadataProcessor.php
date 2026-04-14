<?php

namespace BlueFission\Automata\Media\Processing\Image;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\DevElation as Dev;

class MetadataProcessor implements IProcessor
{
    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $meta = $item->meta();
        $width = $meta['width'] ?? null;
        $height = $meta['height'] ?? null;

        if ($width && $height) {
            $result->addFeature('width', (int)$width);
            $result->addFeature('height', (int)$height);
            $result->addFeature('aspect_ratio', $height > 0 ? $width / $height : 0.0);
        }

        Dev::do('media.processing.image.meta', ['width' => $width, 'height' => $height]);

        return $result;
    }
}
