<?php

namespace BlueFission\Automata\Qualification;

use BlueFission\Arr;
use BlueFission\Num;

class QualificationScorer
{
    protected array $criteria;

    public function __construct(
        array $criteria,
        protected float $qualifiedThreshold = 0.7,
        protected float $nurtureThreshold = 0.4
    ) {
        $this->criteria = array_map(
            static fn (mixed $criterion): QualificationCriterion => $criterion instanceof QualificationCriterion
                ? $criterion
                : new QualificationCriterion(Arr::make($criterion)->toArray()),
            $criteria
        );
    }

    public function score(string $subjectId, array $signals, array $trace = []): QualificationScore
    {
        $totalWeight = 0.0;
        $observedWeight = 0.0;
        $weightedScore = 0.0;
        $weightedConfidence = 0.0;
        $reasons = [];
        $unmet = [];
        $evidence = [];

        foreach ($this->criteria as $criterion) {
            $weight = $criterion->weight();
            $totalWeight += $weight;
            $key = $criterion->key();
            if ($key === '' || !Arr::hasKey($signals, $key)) {
                if ($criterion->required()) {
                    $unmet[] = $key;
                    $reasons[] = 'missing_required:' . $key;
                }
                continue;
            }

            $signal = $signals[$key];
            $payload = Arr::is($signal) ? Arr::make($signal)->toArray() : ['value' => $signal];
            $value = Num::isValid($payload['value'] ?? null) ? (float)$payload['value'] : 0.0;
            $value = Num::min(1.0, Num::max(0.0, $value));
            $confidence = Num::isValid($payload['confidence'] ?? null) ? (float)$payload['confidence'] : 1.0;
            $confidence = Num::min(1.0, Num::max(0.0, $confidence));

            $observedWeight += $weight;
            $weightedScore += $value * $weight;
            $weightedConfidence += $confidence * $weight;
            if (Arr::hasKey($payload, 'evidence')) {
                $evidence[$key] = $payload['evidence'];
            }
            if ($criterion->required() && $value < $criterion->minimum()) {
                $unmet[] = $key;
                $reasons[] = 'below_minimum:' . $key;
            }
        }

        $score = $observedWeight > 0.0 ? $weightedScore / $observedWeight : 0.0;
        $coverage = $totalWeight > 0.0 ? $observedWeight / $totalWeight : 0.0;
        $confidence = $observedWeight > 0.0
            ? ($weightedConfidence / $observedWeight) * $coverage
            : 0.0;

        return new QualificationScore([
            'subject_id' => $subjectId,
            'score' => $score,
            'confidence' => $confidence,
            'status' => $this->status($score, $unmet),
            'reasons' => $reasons,
            'unmet_criteria' => array_values(array_unique($unmet)),
            'evidence' => $evidence,
            'trace' => $trace,
        ]);
    }

    protected function status(float $score, array $unmet): string
    {
        if ($unmet !== []) {
            return QualificationScore::STATUS_REVIEW;
        }
        if ($score >= Num::min(1.0, Num::max(0.0, $this->qualifiedThreshold))) {
            return QualificationScore::STATUS_QUALIFIED;
        }
        if ($score >= Num::min(1.0, Num::max(0.0, $this->nurtureThreshold))) {
            return QualificationScore::STATUS_NURTURE;
        }

        return QualificationScore::STATUS_DISQUALIFIED;
    }
}
