<?php

namespace BlueFission\Automata\Feedback;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\DevElation as Dev;

class Assessor
{
    protected array $_strategies = [];
    protected OrganizedCollection $_projections;

    public function __construct()
    {
        $this->_projections = new OrganizedCollection();
    }

    public function addStrategy(IAssessmentStrategy $strategy): void
    {
        $this->_strategies[] = $strategy;
        Dev::do('feedback.assessor.strategy_added', ['strategy' => $strategy->name()]);
    }

    public function addProjection(Projection $projection, string $key = null): void
    {
        $key = $key ?? uniqid('projection_', true);
        $this->_projections->add($projection, $key);
        Dev::do('feedback.assessor.projection_added', ['key' => $key, 'projection' => $projection]);
    }

    public function assess(Projection $projection, Observation $observation): Assessment
    {
        $bestScore = 0.0;
        $bestStrategy = '';

        foreach ($this->_strategies as $strategy) {
            $score = $strategy->score($projection, $observation);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestStrategy = $strategy->name();
            }
        }

        $matched = $bestScore > 0.0;
        $assessment = new Assessment($matched, $bestScore, $bestStrategy);

        Dev::do('feedback.assessor.assessed', [
            'projection' => $projection,
            'observation' => $observation,
            'assessment' => $assessment,
        ]);

        return $assessment;
    }
}
