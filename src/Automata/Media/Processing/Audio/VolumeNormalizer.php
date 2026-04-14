<?php

namespace BlueFission\Automata\Media\Processing\Audio;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\HandlerRegistry;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\DevElation as Dev;

class VolumeNormalizer implements IProcessor
{
    protected $handler;

    public function __construct($handler = null)
    {
        $this->handler = $handler;
    }

    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $handler = $options['volume_normalizer'] ?? $this->handler;
        $registry = $options['registry'] ?? $options['handler_registry'] ?? null;
        if (!$handler && $registry instanceof HandlerRegistry) {
            $handler = $registry->resolve('volume_normalizer');
        }

        if (is_callable($handler)) {
            $level = call_user_func($handler, $item, $context, $options);
            $level = Dev::apply('media.processing.audio.volume', $level);
            if ($level !== null) {
                $result->addFeature('volume_level', $level);
            }
        }

        return $result;
    }
}
