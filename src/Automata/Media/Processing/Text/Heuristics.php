<?php

namespace BlueFission\Automata\Media\Processing\Text;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\DevElation as Dev;

class Heuristics implements IProcessor
{
    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $text = (string)($result->meta()['normalized_text'] ?? $item->content());
        $tokens = $result->tokens();

        $metrics = [
            'length' => strlen($text),
            'token_count' => count($tokens),
            'unique_tokens' => count(array_unique($tokens)),
            'sentence_count' => max(1, preg_match_all('/[.!?]+/', $text)),
        ];

        $metrics = Dev::apply('media.processing.text.heuristics', $metrics);

        foreach ($metrics as $name => $value) {
            $result->setMetric($name, $value);
        }

        return $result;
    }
}
