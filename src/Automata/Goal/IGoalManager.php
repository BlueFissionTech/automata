<?php

namespace BlueFission\Automata\Goal;

use BlueFission\Automata\Context;

interface IGoalManager
{
    public const DEFAULT_ACTION = 'continue';
    public const DEFAULT_LIMIT = 5;

    /**
     * Add an active initiative to the manager.
     */
    public function addGoal(Initiative $goal): self;

    /**
     * Remove a goal by key.
     */
    public function removeGoal(string $key): self;

    /**
     * Return a stable key for an initiative.
     */
    public function goalKey(Initiative $goal): string;

    /**
     * Return active goals.
     */
    public function goals(): array;

    /**
     * Return active goals sorted by weight or priority.
     */
    public function topGoals(int $limit = self::DEFAULT_LIMIT): array;

    /**
     * Register an expectation that a behavior should satisfy a criterion.
     */
    public function registerExpectation(string $behavior, string $criterionKey, float $ttlSeconds = 60.0, string $reason = ''): GoalExpectation;

    /**
     * Return tracked expectations.
     */
    public function expectations(): array;

    /**
     * Mark satisfiable criteria from deterministic context data.
     */
    public function updateCriteriaSatisfied(array|Context $context = []): array;

    /**
     * Check expectations against current criteria and return status rows.
     */
    public function checkExpectations(array|Context $context = []): array;

    /**
     * Score a behavior against all active unsatisfied criteria.
     */
    public function rateBehaviorResolution(string $behavior, array|Context $context = []): float;

    /**
     * Recommend bounded decision options from active goals and supplied context.
     */
    public function recommend(array|Context $context = [], int $limit = self::DEFAULT_LIMIT): array;

    /**
     * Reinforce a behavior association for a criterion key.
     */
    public function reinforceBehavior(string $behavior, string|InitiativeObject $criterion, float $score, bool $success = true): self;

    /**
     * Determine whether any active criterion with this key is satisfied.
     */
    public function isCriterionSatisfied(string $criterionKey): bool;

    /**
     * Export active goals, expectations, and behavior associations.
     */
    public function toArray(): array;
}
