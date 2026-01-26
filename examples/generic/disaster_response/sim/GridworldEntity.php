<?php

namespace Examples\DisasterResponse\Sim;

use BlueFission\Automata\Classification\Gateway;
use BlueFission\Automata\Context;
use BlueFission\Automata\Feedback\Assessor;
use BlueFission\Automata\Feedback\FeedbackRegistry;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Feedback\Observation;
use BlueFission\Automata\Feedback\Projection;
use BlueFission\Automata\GameTheory\Game;
use BlueFission\Automata\Goal\Initiative;
use BlueFission\Automata\Goal\Objective;
use BlueFission\Automata\Simulation\ISimulatable;

class GridworldEntity implements ISimulatable
{
    private Grid $grid;
    private ResponderPlayer $player;
    private ResponderStrategy $strategy;
    private Initiative $initiative;
    private Gateway $gateway;
    private Assessor $assessor;
    private FeedbackRegistry $feedback;
    /** @var array<string, int> */
    private array $progress;
    /** @var array<string, Objective> */
    private array $objectiveMap;

    public function __construct(
        Grid $grid,
        ResponderPlayer $player,
        ResponderStrategy $strategy,
        Initiative $initiative,
        Gateway $gateway,
        Assessor $assessor,
        FeedbackRegistry $feedback
    ) {
        $this->grid = $grid;
        $this->player = $player;
        $this->strategy = $strategy;
        $this->initiative = $initiative;
        $this->gateway = $gateway;
        $this->assessor = $assessor;
        $this->feedback = $feedback;

        $this->player->setStrategy($this->strategy);

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
    }

    public function step(int $tick, array &$worldState): void
    {
        $position = $this->player->position();
        $cell = $this->grid->cell($position);
        if (!$cell) {
            $worldState['last'] = ['tick' => $tick];
            return;
        }

        $context = new Context();
        $context->set('tick', $tick);
        $context->set('position', $position->toArray());
        $context->set('cell_type', $cell->type());

        $classification = $this->gateway->classify($cell, ['context' => $context]);
        $classificationTags = array_keys($classification->tags());

        $this->strategy->setContext([
            'tags' => $classificationTags,
            'satisfied' => $this->satisfiedMetrics(),
        ]);

        $game = new Game(1);
        $game->addPlayer($this->player);
        $game->play();

        $action = $this->player->lastDecision() ?? 'move';
        $success = $this->performAction($action, $cell);

        if ($action === 'move') {
            $this->moveAgent();
        }

        $observationTags = array_values(array_unique(array_merge(
            [$action, 'behavior_' . $action],
            $cell->tags()
        )));

        $observation = new Observation([
            'tags' => $observationTags,
            'context' => [
                'tick' => $tick,
                'position' => $position->toArray(),
                'cell_type' => $cell->type(),
                'action' => $action,
            ],
        ]);

        $assessment = $this->assessObservation($observation);
        $this->applyFeedback($action, $assessment->score(), $assessment->matched());

        $worldState['last'] = [
            'tick' => $tick,
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
        ];

        $worldState['progress'] = $this->progress;
        $worldState['feedback'] = $this->feedbackScores();
        $worldState['agent'] = $position->toArray();
        $worldState['grid_snapshot'] = $this->snapshotGrid();
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
        $current = $this->player->position();
        $target = $this->grid->bestNeighbor($current);
        if (!$target) {
            return;
        }

        if ($target->equals($current)) {
            return;
        }

        $this->player->move($target, $this->grid);
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

    private function satisfiedMetrics(): array
    {
        $satisfied = [];
        foreach ($this->objectiveMap as $metric => $objective) {
            if ($objective instanceof Objective && (bool)$objective->field('is_satisfied')) {
                $satisfied[] = $metric;
            }
        }
        return $satisfied;
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

        return $bestAssessment ?? $this->assessor->assess(new Projection(), $observation);
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

    private function snapshotGrid(): array
    {
        $cells = [];
        for ($y = 0; $y < $this->grid->height(); $y++) {
            for ($x = 0; $x < $this->grid->width(); $x++) {
                $position = new Position($x, $y);
                $cell = $this->grid->cell($position);
                if (!$cell) {
                    continue;
                }

                $cells[] = [
                    'x' => $x,
                    'y' => $y,
                    'type' => $cell->type(),
                    'blocked' => $cell->isBlocked(),
                    'damaged' => $cell->isDamaged(),
                    'people' => $cell->people(),
                    'supplies' => $cell->supplies(),
                ];
            }
        }

        return [
            'width' => $this->grid->width(),
            'height' => $this->grid->height(),
            'cells' => $cells,
            'agent' => $this->player->position()->toArray(),
        ];
    }
}
