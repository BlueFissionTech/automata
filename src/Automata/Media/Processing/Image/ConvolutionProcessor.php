<?php

namespace BlueFission\Automata\Media\Processing\Image;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\HandlerRegistry;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\DevElation as Dev;

class ConvolutionProcessor implements IProcessor
{
    protected $handler;

    public function __construct($handler = null)
    {
        $this->handler = $handler;
    }

    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $handler = $options['convolution'] ?? $this->handler;
        $registry = $options['registry'] ?? $options['handler_registry'] ?? null;
        if (!$handler && $registry instanceof HandlerRegistry) {
            $handler = $registry->resolve('convolution');
        }

        if (is_callable($handler)) {
            $features = call_user_func($handler, $item, $context, $options);
            $features = Dev::apply('media.processing.image.convolution', $features);
            if (is_array($features)) {
                $result->addFeature('convolution', $features);
            }
        }

        return $result;
    }
}
