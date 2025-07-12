<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\GraphTheory\Graph;
use BlueFission\Automata\GraphTheory\Node as GraphNode;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Collections\ICollection;
use BlueFission\Automata\Context;

class MemoryNode extends GraphNode {
    protected Context $context;

    public function __construct(string $name, array $edges = [], ?Context $context = null) {
        parent::__construct($name, $edges);
        $this->context = $context ?? new Context();
    }

    public function getContext() {
        return $this->context;
    }

    public function setContext($context) {
        $this->context = $context;
    }

    public function reinforce(float $amount = 1.0): void {
        // This could eventually decay less, increase internal weight, etc.
        $this->context->set('reinforcement', ($this->context->get('reinforcement', 0) + $amount));
    }

    public function similarity(Context $other): float {
        $currentData = $this->context->all();
        $otherData = $other->all();

        if (empty($currentData) && empty($otherData)) {
            return 1.0;
        }

        // Use cosine similarity if values are vectors
        if ($this->isVector($currentData) && $this->isVector($otherData)) {
            return $this->cosineSimilarity($currentData, $otherData);
        }

        // Use Levenshtein similarity if values are scalar strings
        if ($this->isScalarString($currentData) && $this->isScalarString($otherData)) {
            $string1 = implode(' ', $currentData);
            $string2 = implode(' ', $otherData);
            $distance = levenshtein($string1, $string2);
            $maxLength = max(strlen($string1), strlen($string2));
            return $maxLength > 0 ? 1 - ($distance / $maxLength) : 1.0;
        }

        // Fallback: intersection over union
        $intersection = array_intersect_assoc($currentData, $otherData);
        $union = array_merge($currentData, $otherData);

        return count($intersection) / max(count($union), 1);
    }

    private function isVector(array $data): bool {
        return !empty($data) && is_numeric(reset($data));
    }

    private function isScalarString(array $data): bool {
        return !empty($data) && is_string(reset($data));
    }

    private function cosineSimilarity(array $vec1, array $vec2): float {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($vec1 as $key => $value) {
            $dotProduct += $value * ($vec2[$key] ?? 0);
            $normA += $value * $value;
        }

        foreach ($vec2 as $value) {
            $normB += $value * $value;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}

