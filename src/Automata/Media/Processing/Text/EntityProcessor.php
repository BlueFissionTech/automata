<?php

namespace BlueFission\Automata\Media\Processing\Text;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\Automata\Language\EntityExtractor;
use BlueFission\DevElation as Dev;

class EntityProcessor implements IProcessor
{
    protected EntityExtractor $extractor;

    public function __construct(?EntityExtractor $extractor = null)
    {
        $this->extractor = $extractor ?? new EntityExtractor();
    }

    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $text = $result->meta()['normalized_text'] ?? $item->content();

        $entities = [
            'email' => $this->extractor->email($text),
            'url' => $this->extractor->web($text),
            'date' => $this->extractor->date($text),
            'time' => $this->extractor->time($text),
            'phone' => $this->extractor->phone($text),
            'tag' => $this->extractor->tags($text),
            'mention' => $this->extractor->mentions($text),
        ];

        $entities = Dev::apply('media.processing.text.entities', $entities);

        foreach ($entities as $label => $values) {
            if (!empty($values)) {
                $result->addEntity($label, $values);
            }
        }

        return $result;
    }
}
