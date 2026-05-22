<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\Path\Node as GraphNode;
use BlueFission\DevElation as Dev;
use BlueFission\Num;
use BlueFission\Str;

class MemoryNode extends GraphNode
{
    protected Context $context;

    public function __construct(string $name, array $edges = [], ?Context $context = null)
    {
        $this->context = $context ?? new Context();
        $this->context = Dev::apply('automata.memory.memorynode.__construct.1', $this->context);

        parent::__construct($name, $this->context, $edges);

        Dev::do('automata.memory.memorynode.__construct.action1', ['name' => $name, 'context' => $this->context]);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
        $this->data = $context;
    }

    /**
     * Reinforce the importance of this memory in-place.
     */
    public function reinforce(float $amount = 1.0): void
    {
        $current = (float)$this->context->get('reinforcement', 0);
        $this->context->set('reinforcement', $current + $amount);
    }

    /**
     * Coarse similarity between this node's context and another context.
     *
     * - If values look like numeric vectors, use cosine similarity.
     * - If values are strings, use Levenshtein distance on concatenated text.
     * - Otherwise, fall back to intersection-over-union on key/value pairs.
     */
    public function similarity(Context $other): float
    {
        $currentData = $this->context->all();
        $otherData = $other->all();

        if (Arr::count($currentData) === 0 && Arr::count($otherData) === 0) {
            return 1.0;
        }

        if ($this->isVector($currentData) && $this->isVector($otherData)) {
            return $this->cosineSimilarity($currentData, $otherData);
        }

        if ($this->isScalarString($currentData) && $this->isScalarString($otherData)) {
            $string1 = $this->joinStrings($currentData);
            $string2 = $this->joinStrings($otherData);

            if ($string1 === '' && $string2 === '') {
                return 1.0;
            }

            $distance = levenshtein($string1, $string2);
            $maxLength = max(Str::len($string1), Str::len($string2));

            return $maxLength > 0 ? 1 - ($distance / $maxLength) : 1.0;
        }

        $intersection = $this->intersection($currentData, $otherData);
        $union = $this->union($currentData, $otherData);

        return Arr::count($union) > 0 ? Arr::count($intersection) / Arr::count($union) : 0.0;
    }

    private function isVector(array $data): bool
    {
        return Arr::count($data) > 0 && Num::is($this->firstValue($data));
    }

    private function isScalarString(array $data): bool
    {
        return Arr::count($data) > 0 && Str::is($this->firstValue($data));
    }

    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $keys = Arr::make($this->combineValues(Arr::keys($vec1), Arr::keys($vec2)))->unique()->toArray();

        foreach ($keys as $key) {
            $a = (float)($vec1[$key] ?? 0);
            $b = (float)($vec2[$key] ?? 0);

            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Join string-like context values without raw implode.
     */
    private function joinStrings(array $values): string
    {
        $output = '';
        foreach ($values as $value) {
            $output .= $output === '' ? (string)$value : ' ' . (string)$value;
        }

        return $output;
    }

    /**
     * Return the first value from an array without moving global pointers.
     */
    private function firstValue(array $values): mixed
    {
        foreach ($values as $value) {
            return $value;
        }

        return null;
    }

    /**
     * Return key/value pairs shared by both arrays.
     */
    private function intersection(array $left, array $right): array
    {
        $intersection = [];
        foreach ($left as $key => $value) {
            if (Arr::hasKey($right, $key) && $right[$key] === $value) {
                $intersection[$key] = $value;
            }
        }

        return $intersection;
    }

    /**
     * Return right-biased union without raw array merge helpers.
     */
    private function union(array $left, array $right): array
    {
        $union = $left;
        foreach ($right as $key => $value) {
            $union[$key] = $value;
        }

        return $union;
    }

    /**
     * Append list values without raw array merge helpers.
     */
    private function combineValues(array $left, array $right): array
    {
        $combined = [];
        foreach ($left as $value) {
            $combined[] = $value;
        }

        foreach ($right as $value) {
            $combined[] = $value;
        }

        return $combined;
    }
}

