<?php

namespace BlueFission\Automata\Anomaly;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\DevElation as Dev;

class Result
{
    protected OrganizedCollection $_scores;

    public function __construct(array $scores = [])
    {
        $this->_scores = new OrganizedCollection();

        foreach ($scores as $label => $score) {
            if (is_int($label)) {
                $label = (string)$score;
                $score = 0.0;
            }
            $this->addIndicator((string)$label, (float)$score);
        }
    }

    public function addIndicator(string $label, float $score = 0.0, array $meta = []): self
    {
        $label = Dev::apply('anomaly.result.add_indicator.label', $label);
        $score = (float)Dev::apply('anomaly.result.add_indicator.score', $score);

        $indicator = new Indicator($label, $score, $meta);
        $this->_scores->add($indicator, $label);
        $this->_scores->weight($label, $score);
        $this->_scores->sort();

        Dev::do('anomaly.result.indicator_added', ['label' => $label, 'score' => $score]);

        return $this;
    }

    public function score(string $label): float
    {
        $weight = $this->_scores->weight($label);
        return is_numeric($weight) ? (float)$weight : 0.0;
    }

    public function indicators(): array
    {
        $output = [];
        foreach ($this->_scores->contents() as $label => $entry) {
            $indicator = $entry['value'] ?? null;
            if ($indicator instanceof Indicator) {
                $output[$label] = $indicator->toArray();
            }
        }

        return $output;
    }

    public function top(int $limit = 5): array
    {
        $limit = max(1, $limit);
        $output = [];

        foreach ($this->_scores->contents() as $label => $entry) {
            if (count($output) >= $limit) {
                break;
            }

            $indicator = $entry['value'] ?? null;
            if ($indicator instanceof Indicator) {
                $output[] = [
                    'label' => $indicator->label(),
                    'score' => $indicator->score(),
                    'meta' => $indicator->meta(),
                ];
            }
        }

        return $output;
    }

    public function anomalies(): array
    {
        $output = [];
        foreach ($this->_scores->contents() as $label => $entry) {
            $indicator = $entry['value'] ?? null;
            if ($indicator instanceof Indicator && $indicator->flagged()) {
                $output[$label] = $indicator->toArray();
            }
        }

        return $output;
    }

    public function merge(Result $other, float $weight = 1.0): self
    {
        foreach ($other->indicators() as $label => $indicator) {
            $score = (float)($indicator['score'] ?? 0.0);
            $this->addIndicator($label, $score * $weight, $indicator['meta'] ?? []);
        }

        return $this;
    }
}
