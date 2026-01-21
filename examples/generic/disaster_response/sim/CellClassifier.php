<?php

namespace Examples\DisasterResponse\Sim;

use BlueFission\Automata\Classification\IClassifier;
use BlueFission\Automata\Classification\Result;
use BlueFission\Automata\Context;

class CellClassifier implements IClassifier
{
    private float $accuracy = 0.0;

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $this->accuracy = 0.75;
        return $this;
    }

    public function classify($input, Context $context, array $options = []): Result
    {
        $result = new Result();

        if (!$input instanceof Cell) {
            return $result;
        }

        $tags = $input->tags();
        foreach ($tags as $tag) {
            $score = 0.5;
            if ($tag === 'people') {
                $score = min(1.0, 0.6 + ($input->people() * 0.1));
            } elseif ($tag === 'supplies') {
                $score = min(1.0, 0.4 + ($input->supplies() * 0.1));
            } elseif ($tag === 'blocked' || $tag === 'damage') {
                $score = 0.7;
            }
            $result->addTag($tag, $score, ['source' => 'cell_state']);
        }

        if (in_array('blocked', $tags, true)) {
            $result->addTag('clear', 0.6, ['source' => 'recommendation']);
            $result->relateTags('blocked', 'clear', 0.8);
        }
        if (in_array('damage', $tags, true)) {
            $result->addTag('repair', 0.6, ['source' => 'recommendation']);
            $result->relateTags('damage', 'repair', 0.8);
        }
        if (in_array('people', $tags, true)) {
            $result->addTag('rescue', 0.7, ['source' => 'recommendation']);
            $result->relateTags('people', 'rescue', 0.9);
        }
        if (in_array('supplies', $tags, true)) {
            $result->addTag('deliver', 0.5, ['source' => 'recommendation']);
            $result->relateTags('supplies', 'deliver', 0.6);
        }

        return $result;
    }

    public function accuracy(): float
    {
        return $this->accuracy;
    }

    public function saveModel(string $path): bool
    {
        return false;
    }

    public function loadModel(string $path): bool
    {
        return false;
    }
}
