<?php

namespace Examples\DisasterResponse\Sim;

use BlueFission\Automata\Classification\Gateway;
use BlueFission\Automata\Feedback\Assessor;
use BlueFission\Automata\Feedback\FeedbackRegistry;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Feedback\Observation;
use BlueFission\Automata\Goal\Initiative;
use BlueFission\Automata\Goal\Objective;
use BlueFission\Automata\Context;

class Simulation
{
    private Grid $grid;
    private Agent $agent;
    private Initiative $initiative;
    private Gateway $gateway;
    private Assessor $assessor;
    private FeedbackRegistry $feedback;
    private int $seed;
    private int $tick = 0;
    /** @var array<string, int> */
    private array $progress;
    /** @var array<string, Objective> */
    private array $objectiveMap;

    public function __construct(
        Grid $grid,
        Agent $agent,
        Initiative $initiative,
        Gateway $gateway,
        Assessor $assessor,
        FeedbackRegistry $feedback,
        int $seed = 1
    ) {
        $this->grid = $grid;
        $this->agent = $agent;
        $this->initiative = $initiative;
        $this->gateway = $gateway;
        $this->assessor = $assessor;
        $this->feedback = $feedback;
        $this->seed = $seed;

        $this->progress = [
            'rescue' => 0,
            'clear' => 0,
            'repair' => 0,
            'deliver' => 0,
        ];

        $this->objectiveMap = [];
        foreach ($initiative->objectives() as $objective) {
            if ($objective instanceof Objective) {
                $metric = (string)($objective->field('metric') ?? '');
                if ($metric !== '') {
                    $this->objectiveMap[$metric] = $objective;
                }
            }
        }

        mt_srand($seed);
    }

    public function run(int $ticks): array
    {
        $timeline = [];
        for ($i = 0; $i < $ticks; $i++) {
            $timeline[] = $this->step();
        }

        return [
            'seed' => $this->seed,
            'ticks' => $ticks,
            'grid' => ['width' => $this->grid->width(), 'height' => $this->grid->height()],
            'progress' => $this->progress,
            'feedback' => $this->feedbackScores(),
            'timeline' => $timeline,
        ];
    }

    public function step(): array
    {
        $position = $this->agent->position();
        $cell = $this->grid->cell($position);
        if (!$cell) {
            return ['tick' => $this->tick++];
        }

        $context = new Context();
        $context->set('tick', $this->tick);
        $context->set('position', $position->toArray());
        $context->set('cell_type', $cell->type());

        $classification = $this->gateway->classify($cell, ['context' => $context]);
        $action = $this->chooseAction($cell, array_keys($classification->tags()));

        $cellTags = $cell->tags();
        $success = $this->performAction($action, $cell);

        if ($action === 'move') {
            $this->moveAgent();
        }

        $observationTags = array_values(array_unique(array_merge(
            [$action, 'behavior_' . $action],
            $cellTags
        )));

        $observation = new Observation([
            'tags' => $observationTags,
            'context' => [
                'tick' => $this->tick,
                'position' => $position->toArray(),
                'cell_type' => $cell->type(),
                'action' => $action,
            ],
        ]);

        $assessment = $this->assessObservation($observation);
        $this->applyFeedback($action, $assessment->score(), $assessment->matched());

        $snapshot = [
            'tick' => $this->tick,
            'position' => $position->toArray(),
            'cell_type' => $cell->type(),
            'action' => $action,
            'success' => $success,
            'classification' => $classification->top(4),
            'assessment' => [
                'matched' => $assessment->matched(),
                'score' => $assessment->score(),
                'strategy' => $assessment->strategy(),
            ],
            'progress' => $this->progress,
        ];

        $this->tick++;
        return $snapshot;
    }

    private function chooseAction(Cell $cell, array $classificationTags): string
    {
        $hasPeople = in_array('people', $classificationTags, true) || $cell->people() > 0;
        $hasBlocked = in_array('blocked', $classificationTags, true) || $cell->isBlocked();
        $hasDamage = in_array('damage', $classificationTags, true) || $cell->isDamaged();
        $hasSupplies = in_array('supplies', $classificationTags, true) || $cell->supplies() > 0;

        if (!$this->isSatisfied('rescue') && $hasPeople) {
            return 'rescue';
        }
        if (!$this->isSatisfied('clear') && $hasBlocked) {
            return 'clear';
        }
        if (!$this->isSatisfied('repair') && $hasDamage) {
            return 'repair';
        }
        if (!$this->isSatisfied('deliver') && $hasSupplies) {
            return 'deliver';
        }

        return 'move';
    }

    private function performAction(string $action, Cell $cell): bool
    {
        $success = false;

        switch ($action) {
            case 'rescue':
                $success = $cell->rescue();
                break;
            case 'clear':
                $success = $cell->clear();
                break;
            case 'repair':
                $success = $cell->repair();
                break;
            case 'deliver':
                $success = $cell->deliver();
                break;
        }

        if ($success) {
            $this->recordProgress($action);
        }

        return $success;
    }

    private function moveAgent(): void
    {
        $current = $this->agent->position();
        $target = $this->grid->bestNeighbor($current);
        if (!$target) {
            return;
        }

        if ($target->equals($current)) {
            return;
        }

        $this->agent->move($target, $this->grid);
    }

    private function recordProgress(string $metric): void
    {
        $this->progress[$metric] = ($this->progress[$metric] ?? 0) + 1;

        $objective = $this->objectiveMap[$metric] ?? null;
        if (!$objective) {
            return;
        }

        $target = (int)($objective->field('target') ?? $objective->field('value') ?? 0);
        $objective->field('progress', $this->progress[$metric]);
        if ($target > 0 && $this->progress[$metric] >= $target) {
            $objective->field('is_satisfied', true);
        }
    }

    private function isSatisfied(string $metric): bool
    {
        $objective = $this->objectiveMap[$metric] ?? null;
        if (!$objective) {
            return false;
        }

        return (bool)$objective->field('is_satisfied');
    }

    private function assessObservation(Observation $observation)
    {
        $bestAssessment = null;
        $bestScore = 0.0;

        foreach ($this->initiative->buildProjections() as $projection) {
            if ($projection->isExpired()) {
                continue;
            }
            $assessment = $this->assessor->assess($projection, $observation);
            if ($assessment->score() > $bestScore) {
                $bestScore = $assessment->score();
                $bestAssessment = $assessment;
            }
        }

        return $bestAssessment ?? $this->assessor->assess(new \BlueFission\Automata\Feedback\Projection(), $observation);
    }

    private function applyFeedback(string $action, float $score, bool $matched): void
    {
        if ($matched) {
            $signal = FeedbackSignal::positive(max(0.1, $score));
        } else {
            $signal = FeedbackSignal::negative(0.1);
        }

        $this->feedback->apply('behavior_' . $action, $signal);
    }

    private function feedbackScores(): array
    {
        $scores = [];
        foreach (array_keys($this->progress) as $metric) {
            $scores['behavior_' . $metric] = $this->feedback->score('behavior_' . $metric);
        }

        return $scores;
    }
}
