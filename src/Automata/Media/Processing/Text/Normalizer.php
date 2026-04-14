<?php

namespace BlueFission\Automata\Media\Processing\Text;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\Automata\Language\ContractionNormalizer;
use BlueFission\DevElation as Dev;

class Normalizer implements IProcessor
{
    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $text = (string)$item->content();
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = ContractionNormalizer::normalize($text);
        $text = trim($text);

        $lowercase = $options['lowercase'] ?? true;
        if ($lowercase) {
            $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        }

        $text = Dev::apply('media.processing.text.normalized', $text);

        $result->setMeta('normalized_text', $text);
        $context->setNormalization('text', ['normalized' => $text]);

        return $result;
    }
}
