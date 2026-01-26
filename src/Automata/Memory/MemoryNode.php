<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\Context;
use BlueFission\Automata\Path\Node as GraphNode;
use BlueFission\DevElation as Dev;

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

        if (empty($currentData) && empty($otherData)) {
            return 1.0;
        }

        if ($this->isVector($currentData) && $this->isVector($otherData)) {
            return $this->cosineSimilarity($currentData, $otherData);
        }

        if ($this->isScalarString($currentData) && $this->isScalarString($otherData)) {
            $string1 = implode(' ', $currentData);
            $string2 = implode(' ', $otherData);

            if ($string1 === '' && $string2 === '') {
                return 1.0;
            }

            $distance = levenshtein($string1, $string2);
            $maxLength = max(strlen($string1), strlen($string2));

            return $maxLength > 0 ? 1 - ($distance / $maxLength) : 1.0;
        }

        $intersection = array_intersect_assoc($currentData, $otherData);
        $union = array_merge($currentData, $otherData);

        return count($union) > 0 ? count($intersection) / count($union) : 0.0;
    }

    private function isVector(array $data): bool
    {
        return !empty($data) && is_numeric(reset($data));
    }

    private function isScalarString(array $data): bool
    {
        return !empty($data) && is_string(reset($data));
    }

    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $keys = array_unique(array_merge(array_keys($vec1), array_keys($vec2)));

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
}

