<?php

namespace BlueFission\Automata\Classification;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\DevElation as Dev;

class Result
{
    protected OrganizedCollection $_tags;
    protected Graph $_graph;

    public function __construct(array $tags = [])
    {
        $this->_tags = new OrganizedCollection();
        $this->_graph = new Graph();

        foreach ($tags as $label => $score) {
            if (is_int($label)) {
                $label = (string)$score;
                $score = 1.0;
            }
            $this->addTag((string)$label, (float)$score);
        }
    }

    public function addTag(string $label, float $score = 1.0, array $meta = []): self
    {
        $label = Dev::apply('classification.result.add_tag.label', $label);
        $score = (float)Dev::apply('classification.result.add_tag.score', $score);

        $tag = new Tag($label, $score, $meta);
        $this->_tags->add($tag, $label);
        $this->_tags->weight($label, $score);
        $this->_tags->sort();

        $this->_graph->addTag($label);
        Dev::do('classification.result.tag_added', ['label' => $label, 'score' => $score]);

        return $this;
    }

    public function relateTags(string $from, string $to, float $weight = 1.0): self
    {
        $this->_graph->relate($from, $to, $weight);
        return $this;
    }

    public function score(string $label): float
    {
        $weight = $this->_tags->weight($label);
        return is_numeric($weight) ? (float)$weight : 0.0;
    }

    public function tags(): array
    {
        $output = [];
        foreach ($this->_tags->contents() as $label => $entry) {
            $tag = $entry['value'] ?? null;
            if ($tag instanceof Tag) {
                $output[$label] = $tag->toArray();
            }
        }

        return $output;
    }

    public function top(int $limit = 5): array
    {
        $limit = max(1, $limit);
        $output = [];

        foreach ($this->_tags->contents() as $label => $entry) {
            if (count($output) >= $limit) {
                break;
            }
            $tag = $entry['value'] ?? null;
            if ($tag instanceof Tag) {
                $output[] = [
                    'label' => $tag->label(),
                    'score' => $tag->score(),
                    'meta' => $tag->meta(),
                ];
            }
        }

        return $output;
    }

    public function merge(Result $other, float $weight = 1.0): self
    {
        foreach ($other->tags() as $label => $tag) {
            $score = (float)($tag['score'] ?? 0.0);
            $this->addTag($label, $score * $weight, $tag['meta'] ?? []);
        }

        return $this;
    }

    public function graph(): Graph
    {
        return $this->_graph;
    }
}
