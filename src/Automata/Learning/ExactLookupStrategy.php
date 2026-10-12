<?php

namespace BlueFission\Automata\Learning;

/** Conservative default: repeated identical input and context, with output frequency. */
final class ExactLookupStrategy implements TrainingStrategy
{
    private array $index = [];

    public function train(array $observations): void
    {
        $this->index = [];
        foreach ($observations as $observation) {
            $key = TrainingValue::key($observation->input, $observation->context);
            $outputKey = hash('sha256', serialize($observation->output));
            $this->index[$key]['total'] = ($this->index[$key]['total'] ?? 0) + 1;
            $this->index[$key]['outputs'][$outputKey]['count'] =
                ($this->index[$key]['outputs'][$outputKey]['count'] ?? 0) + 1;
            $this->index[$key]['outputs'][$outputKey]['value'] = $observation->output;
        }
    }

    public function predict(array $input, array $context): TrainingPrediction
    {
        $row = $this->index[TrainingValue::key($input, $context)] ?? null;
        if ($row === null) {
            return TrainingPrediction::unavailable();
        }
        $best = null;
        foreach ($row['outputs'] as $candidate) {
            if ($best === null || $candidate['count'] > $best['count']) {
                $best = $candidate;
            }
        }
        return new TrainingPrediction(true, $best['value'], $best['count'] / $row['total'], $best['count']);
    }
}
