<?php

namespace BlueFission\Automata\Media\Processing\Image;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\HandlerRegistry;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\DevElation as Dev;

class BoundaryProcessor implements IProcessor
{
    protected $handler;

    public function __construct($handler = null)
    {
        $this->handler = $handler;
    }

    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $handler = $options['boundary_detector'] ?? $this->handler;
        $registry = $options['registry'] ?? $options['handler_registry'] ?? null;
        if (!$handler && $registry instanceof HandlerRegistry) {
            $handler = $registry->resolve('boundary_detector');
        }

        if (is_callable($handler)) {
            $boundaries = call_user_func($handler, $item, $context, $options);
            $boundaries = Dev::apply('media.processing.image.boundaries', $boundaries);
            if (is_array($boundaries)) {
                $result->addFeature('boundaries', $boundaries);
            }
        }

        return $result;
    }
}
