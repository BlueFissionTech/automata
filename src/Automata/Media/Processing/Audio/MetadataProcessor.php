<?php

namespace BlueFission\Automata\Media\Processing\Audio;

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

        if (isset($meta['mime'])) {
            $result->setMeta('mime', $meta['mime']);
        }
        if (isset($meta['size'])) {
            $result->setMetric('size_bytes', (int)$meta['size']);
        }

        Dev::do('media.processing.audio.meta', ['meta' => $meta]);

        return $result;
    }
}
