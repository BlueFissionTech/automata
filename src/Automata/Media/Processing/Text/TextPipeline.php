<?php

namespace BlueFission\Automata\Media\Processing\Text;

use BlueFission\Arr;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Pipeline;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\Automata\Context;

class TextPipeline extends Pipeline
{
    public function __construct()
    {
        parent::__construct();
        $this->addProcessor(new Normalizer());
        $this->addProcessor(new Tokenizer());
        $this->addProcessor(new EntityProcessor());
        $this->addProcessor(new BagOfWords());
        $this->addProcessor(new Heuristics());
    }

    public function process(MediaItem $item, ?Context $context = null, array $options = []): Result
    {
        $result = parent::process($item, $context, $options);

        if (Arr::size($result->segments()) === 0) {
            $normalized = $result->meta()['normalized_text'] ?? $item->content();
            $result->addSegment($item->type() ?? 'text', $normalized, [
                'features' => $result->features(),
                'entities' => $result->entities(),
                'metrics' => $result->metrics(),
            ]);
        }

        return $result;
    }
}
