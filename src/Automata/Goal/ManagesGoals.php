<?php

namespace BlueFission\Automata\Goal;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Collections\Collection;
use BlueFission\DevElation as Dev;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;

trait ManagesGoals
{
    protected array $goals = [];
    protected array $expectations = [];
    protected array $associations = [];
    protected int $maxGoals = 20;
    protected int $maxAssociations = 20;

    /**
     * Initialize reusable goal-manager state for default and custom managers.
     */
    protected function initializeGoalManager(array $goals = [], int $maxGoals = 20, int $maxAssociations = 20): void
    {
        $this->maxGoals = $maxGoals;
        $this->maxAssociations = $maxAssociations;

        foreach ($goals as $goal) {
            if ($goal instanceof Initiative) {
                $this->addGoal($goal);
            }
        }
    }

    /**
     * Add an active initiative to the manager.
     */
    public function addGoal(Initiative $goal): self
    {
        $this->goals[$this->goalKey($goal)] = $goal;

        while (Arr::make($this->goals)->count() > $this->maxGoals) {
            $this->removeOldestGoal();
        }

        Dev::do('goal.manager.goal_added', [
            'goal' => $goal,
            'count' => Arr::make($this->goals)->count(),
        ]);

        return $this;
    }

    /**
     * Remove a goal by key.
     */
    public function removeGoal(string $key): self
    {
        if (Arr::hasKey($this->goals, $key)) {
            unset($this->goals[$key]);
        }

        return $this;
    }

    /**
     * Return a stable key for an initiative.
     */
    public function goalKey(Initiative $goal): string
    {
        $key = $goal->field('initiative_id') ?? $goal->field('name');
        if (Val::isNotEmpty($key)) {
            return Str::trim((string)$key);
        }

        return 'goal_' . spl_object_id($goal);
    }

    /**
     * Return active goals.
     */
    public function goals(): array
    {
        return Arr::make($this->goals)->toArray();
    }

    /**
     * Return active goals sorted by weight or priority.
     */
    public function topGoals(int $limit = IGoalManager::DEFAULT_LIMIT): array
    {
        $sorted = (new Collection($this->goals))
            ->sort(fn (Initiative $a, Initiative $b) => $this->goalWeight($b) <=> $this->goalWeight($a))
            ->toArray();

        return $this->limitArray($sorted, $limit);
    }

    /**
     * Register an expectation that a behavior should satisfy a criterion.
     */
    public function registerExpectation(string $behavior, string $criterionKey, float $ttlSeconds = 60.0, string $reason = ''): GoalExpectation
    {
        $expectation = GoalExpectation::forBehavior($behavior, $criterionKey, $ttlSeconds, $reason);
        $this->expectations[] = $expectation;

        Dev::do('goal.manager.expectation_registered', $expectation->toArray());

        return $expectation;
    }

    /**
     * Return tracked expectations.
     */
    public function expectations(): array
    {
        return Arr::make($this->expectations)->toArray();
    }

    /**
     * Mark satisfiable criteria from deterministic context data.
     */
    public function updateCriteriaSatisfied(array|Context $context = []): array
    {
        $context = $this->contextArray($context);
        $updates = [];

        foreach ($this->goals as $goalKey => $goal) {
            foreach ($this->criteria($goal) as $criterion) {
                $criterionKey = $this->criterionKey($criterion);
                if ($this->criterionSatisfied($criterion, $context)) {
                    $this->markSatisfied($criterion);
                    $updates[] = [
                        'goal' => $goalKey,
                        'criterion' => $criterionKey,
                        'satisfied' => true,
                    ];
                }
            }
        }

        if (Arr::make($updates)->count() > 0) {
            Dev::do('goal.manager.criteria_updated', ['updates' => $updates]);
        }

        return $updates;
    }

    /**
     * Check expectations against current criteria and return status rows.
     */
    public function checkExpectations(array|Context $context = []): array
    {
        $this->updateCriteriaSatisfied($context);
        $results = [];

        foreach ($this->expectations as $index => $expectation) {
            $fulfilled = $this->isCriterionSatisfied($expectation->criterionKey());
            if ($fulfilled) {
                $expectation->fulfill();
            }

            $results[] = [
                'expectation' => $expectation->toArray(),
                'fulfilled' => $fulfilled,
                'expired' => $expectation->expired(),
            ];

            if ($fulfilled || $expectation->expired()) {
                unset($this->expectations[$index]);
            }
        }

        $this->expectations = Arr::make($this->expectations)->toArray();

        return $results;
    }

    /**
     * Score a behavior against all active unsatisfied criteria.
     */
    public function rateBehaviorResolution(string $behavior, array|Context $context = []): float
    {
        $this->updateCriteriaSatisfied($context);
        $score = 0.0;

        foreach ($this->goals as $goal) {
            foreach ($this->criteria($goal) as $criterion) {
                if ($this->isSatisfied($criterion)) {
                    continue;
                }

                $score += $this->scoreBehaviorAgainstCriterion($behavior, $criterion) * $this->goalWeight($goal);
            }
        }

        return $score;
    }

    /**
     * Recommend bounded decision options from active goals and supplied context.
     */
    public function recommend(array|Context $context = [], int $limit = IGoalManager::DEFAULT_LIMIT): array
    {
        $context = $this->contextArray($context);
        $this->updateCriteriaSatisfied($context);

        $actions = $this->contextActions($context);
        $options = [];

        foreach ($actions as $action) {
            $options[] = GoalDecision::option((string)$action, $this->rateBehaviorResolution((string)$action, $context), [
                'source' => 'context_action',
            ]);
        }

        foreach ($this->topGoals($limit) as $goal) {
            foreach ($this->criteria($goal) as $criterion) {
                if ($this->isSatisfied($criterion)) {
                    continue;
                }

                $action = $this->criterionAction($criterion);
                $options[] = new GoalDecision([
                    'action' => $action,
                    'score' => $this->scoreBehaviorAgainstCriterion($action, $criterion) * $this->goalWeight($goal),
                    'goal' => $this->goalKey($goal),
                    'criterion' => $this->criterionKey($criterion),
                    'reason' => 'unsatisfied goal criterion',
                    'metadata' => [
                        'criterion' => $this->criterionSummary($criterion),
                    ],
                ]);
            }
        }

        if (Arr::make($options)->count() === 0) {
            $options[] = GoalDecision::option(IGoalManager::DEFAULT_ACTION, 0.0, [
                'source' => 'fallback',
            ]);
        }

        $sorted = (new Collection($options))
            ->sort(fn (GoalDecision $a, GoalDecision $b) => $b->score() <=> $a->score())
            ->toArray();

        return $this->limitArray($this->dedupeDecisions($sorted), $limit);
    }

    /**
     * Reinforce a behavior association for a criterion key.
     */
    public function reinforceBehavior(string $behavior, string|InitiativeObject $criterion, float $score, bool $success = true): self
    {
        $criterionKey = $criterion instanceof InitiativeObject ? $this->criterionKey($criterion) : $criterion;
        $delta = $success ? $score : -$score;
        $list = $this->associations[$criterionKey] ?? [];
        $updated = false;

        foreach ($list as $index => $association) {
            if (($association['behavior'] ?? null) !== $behavior) {
                continue;
            }

            $list[$index]['weight'] = (float)($association['weight'] ?? 0.0) + $delta;
            $list[$index]['frequency'] = (int)($association['frequency'] ?? 0) + 1;
            $updated = true;
        }

        if (!$updated) {
            $list[] = [
                'behavior' => $behavior,
                'weight' => $delta,
                'frequency' => 1,
            ];
        }

        $this->associations[$criterionKey] = $this->trimAssociations($list);

        return $this;
    }

    /**
     * Determine whether any active criterion with this key is satisfied.
     */
    public function isCriterionSatisfied(string $criterionKey): bool
    {
        foreach ($this->goals as $goal) {
            foreach ($this->criteria($goal) as $criterion) {
                if ($this->criterionKey($criterion) === $criterionKey) {
                    return $this->isSatisfied($criterion);
                }
            }
        }

        return false;
    }

    /**
     * Export active goals, expectations, and behavior associations.
     */
    public function toArray(): array
    {
        return [
            'goals' => Arr::make($this->goals)->keys()->toArray(),
            'expectations' => (new Collection($this->expectations))
                ->map(fn (GoalExpectation $expectation) => $expectation->toArray())
                ->toArray(),
            'associations' => $this->associations,
        ];
    }

    /**
     * Return criteria from an initiative.
     */
    protected function criteria(Initiative $goal): array
    {
        return Arr::make($goal->criteria())->toArray();
    }

    /**
     * Build a deterministic criterion key.
     */
    protected function criterionKey(InitiativeObject $criterion): string
    {
        if ($criterion instanceof Criterion) {
            return $criterion->key();
        }

        $type = Str::trim((string)($criterion->field('type') ?? $criterion->field('name') ?? 'criterion'));
        $operator = Str::trim((string)($criterion->field('operator') ?? ComparisonOperator::IS));
        $value = Str::trim((string)($criterion->field('value') ?? $criterion->field('expected') ?? 'value'));

        return Str::lower($type . '_' . $operator . '_' . $value);
    }

    /**
     * Return a compact criterion summary for traces and prompts.
     */
    protected function criterionSummary(InitiativeObject $criterion): array
    {
        return [
            'key' => $this->criterionKey($criterion),
            'type' => $criterion->field('type') ?? $criterion->field('name'),
            'operator' => $criterion->field('operator') ?? ComparisonOperator::IS,
            'value' => $criterion->field('value') ?? $criterion->field('expected'),
            'priority' => $this->criterionPriority($criterion),
        ];
    }

    /**
     * Determine whether a criterion is already satisfied.
     */
    protected function isSatisfied(InitiativeObject $criterion): bool
    {
        if ($criterion instanceof Criterion) {
            return $criterion->satisfied();
        }

        return (bool)$criterion->field('is_satisfied');
    }

    /**
     * Mark a criterion as satisfied when the deterministic context proves it.
     */
    protected function markSatisfied(InitiativeObject $criterion): void
    {
        if ($criterion instanceof Criterion) {
            $criterion->satisfied(true);
            return;
        }

        $criterion->field('is_satisfied', true);
    }

    /**
     * Evaluate one criterion from context.
     */
    protected function criterionSatisfied(InitiativeObject $criterion, array $context): bool
    {
        if ($this->isSatisfied($criterion)) {
            return true;
        }

        if ($criterion instanceof Condition && $criterion->matches($context)) {
            return true;
        }

        $path = (string)($criterion->field('path') ?? $criterion->field('attribute') ?? $criterion->field('name') ?? '');
        $actual = $path !== '' ? Arr::make($context)->getPath($path) : null;
        $expected = $criterion->field('expected') ?? $criterion->field('value');
        $operator = (string)($criterion->field('operator') ?? ComparisonOperator::IS);

        return $this->compare($actual, $expected, $operator);
    }

    /**
     * Compare an observed value against a criterion expectation.
     */
    protected function compare(mixed $actual, mixed $expected, string $operator): bool
    {
        if ($operator === ComparisonOperator::IS_NOT || $operator === 'neq') {
            return $actual !== $expected;
        }

        if ($operator === ComparisonOperator::AT_LEAST || $operator === 'gte') {
            return Num::is($actual) && Num::is($expected) && (float)$actual >= (float)$expected;
        }

        if ($operator === ComparisonOperator::NO_MORE_THAN || $operator === 'lte') {
            return Num::is($actual) && Num::is($expected) && (float)$actual <= (float)$expected;
        }

        return $actual === $expected;
    }

    /**
     * Score a behavior against a single criterion.
     */
    protected function scoreBehaviorAgainstCriterion(string $behavior, InitiativeObject $criterion): float
    {
        $criterionKey = $this->criterionKey($criterion);
        $base = $this->criterionPriority($criterion);
        $configured = $criterion->field('behavior') ?? $criterion->field('action');

        if (Val::isNotEmpty($configured) && (string)$configured === $behavior) {
            return $base + 1.0;
        }

        foreach ($this->associations[$criterionKey] ?? [] as $association) {
            if (($association['behavior'] ?? null) === $behavior) {
                return $base + (float)($association['weight'] ?? 0.0);
            }
        }

        return $base;
    }

    /**
     * Return the deterministic action proposed for a criterion.
     */
    protected function criterionAction(InitiativeObject $criterion): string
    {
        $configured = $criterion->field('behavior') ?? $criterion->field('action');
        if (Val::isNotEmpty($configured)) {
            return (string)$configured;
        }

        $criterionKey = $this->criterionKey($criterion);
        $association = $this->associations[$criterionKey][0] ?? null;
        if (Arr::is($association) && Val::isNotEmpty($association['behavior'] ?? null)) {
            return (string)$association['behavior'];
        }

        return 'satisfy:' . $criterionKey;
    }

    /**
     * Return a criterion priority.
     */
    protected function criterionPriority(InitiativeObject $criterion): float
    {
        $priority = $criterion->field('priority') ?? $criterion->field('weight') ?? 1.0;

        return Num::is($priority) ? (float)$priority : 1.0;
    }

    /**
     * Return a goal weight.
     */
    protected function goalWeight(Initiative $goal): float
    {
        $weight = $goal->field('weight') ?? $goal->field('priority') ?? 1.0;

        return Num::is($weight) ? (float)$weight : 1.0;
    }

    /**
     * Extract possible actions from context.
     */
    protected function contextActions(array $context): array
    {
        $actions = $context['actions'] ?? $context['options'] ?? [];

        return Arr::make($actions)->toArray();
    }

    /**
     * Normalize arrays and Context objects.
     */
    protected function contextArray(array|Context $context): array
    {
        return $context instanceof Context ? $context->all() : $context;
    }

    /**
     * Deduplicate decision options by action name.
     */
    protected function dedupeDecisions(array $decisions): array
    {
        $deduped = [];
        foreach ($decisions as $decision) {
            if (!$decision instanceof GoalDecision) {
                continue;
            }

            $action = $decision->action();
            if (!Arr::hasKey($deduped, $action) || $decision->score() > $deduped[$action]->score()) {
                $deduped[$action] = $decision;
            }
        }

        return Arr::make($deduped)->toArray();
    }

    /**
     * Keep only the first N entries while preserving keys.
     */
    protected function limitArray(array $values, int $limit): array
    {
        $limited = [];
        $count = 0;
        foreach ($values as $key => $value) {
            if ($count >= $limit) {
                break;
            }

            $limited[$key] = $value;
            $count++;
        }

        return $limited;
    }

    /**
     * Remove the oldest goal when the manager exceeds its storage limit.
     */
    protected function removeOldestGoal(): void
    {
        $keys = Arr::make($this->goals)->keys()->toArray();
        $key = $keys[0] ?? null;
        if (Val::isNotEmpty($key)) {
            unset($this->goals[$key]);
        }
    }

    /**
     * Sort and trim behavior associations for one criterion.
     */
    protected function trimAssociations(array $list): array
    {
        $sorted = (new Collection($list))
            ->sort(fn (array $a, array $b) => (float)($b['weight'] ?? 0.0) <=> (float)($a['weight'] ?? 0.0))
            ->toArray();

        return $this->limitArray($sorted, $this->maxAssociations);
    }
}
