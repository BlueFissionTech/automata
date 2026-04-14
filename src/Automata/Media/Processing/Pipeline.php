<?php

namespace BlueFission\Automata\Media\Processing;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Collections\Collection;
use BlueFission\DevElation as Dev;
use BlueFission\Automata\Media\Processing\HandlerRegistry;

class Pipeline
{
    protected Collection $_processors;
    protected ?HandlerRegistry $_registry = null;

    public function __construct()
    {
        $this->_processors = new Collection();
    }

    public function addProcessor($processor, ?string $name = null): void
    {
        if ($name !== null) {
            $this->_processors->add($processor, $name);
            return;
        }

        $this->_processors[] = $processor;
    }

    public function setRegistry(HandlerRegistry $registry): void
    {
        $this->_registry = $registry;
    }

    public function registry(): ?HandlerRegistry
    {
        return $this->_registry;
    }

    public function process(MediaItem $item, ?Context $context = null, array $options = []): Result
    {
        if (!isset($options['handler_registry']) && !isset($options['registry']) && $this->_registry) {
            $options['handler_registry'] = $this->_registry;
        }

        $context = $context ?? $item->context();
        $result = new Result();
        $result->type($item->type());
        $result->context($context);
        $result->meta($item->meta());

        Dev::do('media.processing.pipeline.start', ['type' => $item->type()]);

        foreach ($this->_processors as $processor) {
            $processor = Dev::apply('media.processing.pipeline.processor', $processor);

            if ($processor instanceof IProcessor) {
                $result = $processor->process($item, $context, $result, $options);
            } elseif (is_callable($processor)) {
                $output = call_user_func($processor, $item, $context, $result, $options);
                if ($output instanceof Result) {
                    $result = $output;
                }
            }
        }

        if (empty($result->segments())) {
            $payload = $item->content();
            $meta = $result->meta();
            $result->addSegment($item->type() ?? 'text', $payload, $meta);
        }

        Dev::do('media.processing.pipeline.complete', ['type' => $item->type()]);

        return $result;
    }

    public function train(array $samples, array $labels = [], array $options = []): void
    {
        foreach ($this->_processors as $processor) {
            if ($processor instanceof ITrainable) {
                $processor->train($samples, $labels, $options);
            } elseif (is_callable($processor)) {
                call_user_func($processor, $samples, $labels, $options);
            }
        }
    }
}
