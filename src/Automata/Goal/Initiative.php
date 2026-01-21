<?php

namespace BlueFission\Automata\Goal;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\Context;
use BlueFission\Automata\Feedback\IProjectionBuilder;
use BlueFission\Automata\Feedback\Projection;
use BlueFission\DevElation as Dev;

class Initiative extends InitiativeObject implements IProjectionBuilder
{
    protected OrganizedCollection $_children;
    protected OrganizedCollection $_objectives;
    protected OrganizedCollection $_conditions;

    protected OrganizedCollection $_kpis;
    protected OrganizedCollection $_tasks;
    protected OrganizedCollection $_rewards;
    protected OrganizedCollection $_prerequisites;

    protected ?Initiative $_parent = null;

    public function __construct(array $data = [])
    {
        parent::__construct($data);

        $this->_children = new OrganizedCollection();
        $this->_objectives = new OrganizedCollection();
        $this->_conditions = new OrganizedCollection();
        $this->_kpis = new OrganizedCollection();
        $this->_tasks = new OrganizedCollection();
        $this->_rewards = new OrganizedCollection();
        $this->_prerequisites = new OrganizedCollection();
    }

    public function parent(?Initiative $parent = null): ?Initiative
    {
        if ($parent) {
            $this->_parent = $parent;
            $this->field('parent_initiative_id', $parent->field('initiative_id'));
            Dev::do('goal.initiative.parent_set', ['initiative' => $this, 'parent' => $parent]);
        }

        return $this->_parent;
    }

    public function addChild(Initiative $initiative): self
    {
        $initiative->parent($this);
        $key = $initiative->field('initiative_id') ?? $initiative->field('name') ?? uniqid('initiative_', true);
        $this->_children->add($initiative, (string)$key);
        Dev::do('goal.initiative.child_added', ['initiative' => $this, 'child' => $initiative]);

        return $this;
    }

    public function children(): array
    {
        return array_values(array_map(function ($entry) {
            return $entry['value'] ?? $entry;
        }, $this->_children->contents()));
    }

    public function addObjective(Objective $objective): self
    {
        $key = $objective->field('objective_id') ?? uniqid('objective_', true);
        $this->_objectives->add($objective, (string)$key);
        Dev::do('goal.initiative.objective_added', ['initiative' => $this, 'objective' => $objective]);

        return $this;
    }

    public function addCondition(Condition $condition): self
    {
        $key = $condition->field('condition_id') ?? uniqid('condition_', true);
        $this->_conditions->add($condition, (string)$key);
        Dev::do('goal.initiative.condition_added', ['initiative' => $this, 'condition' => $condition]);

        return $this;
    }

    public function addKpi(Kpi $kpi): self
    {
        $key = $kpi->field('kpi_id') ?? uniqid('kpi_', true);
        $this->_kpis->add($kpi, (string)$key);
        Dev::do('goal.initiative.kpi_added', ['initiative' => $this, 'kpi' => $kpi]);

        return $this;
    }

    public function addTask(Task $task): self
    {
        $key = $task->field('task_id') ?? uniqid('task_', true);
        $this->_tasks->add($task, (string)$key);
        Dev::do('goal.initiative.task_added', ['initiative' => $this, 'task' => $task]);

        return $this;
    }

    public function addReward(Reward $reward): self
    {
        $key = $reward->field('reward_id') ?? uniqid('reward_', true);
        $this->_rewards->add($reward, (string)$key);
        Dev::do('goal.initiative.reward_added', ['initiative' => $this, 'reward' => $reward]);

        return $this;
    }

    public function addPrerequisite(Prerequisite $prerequisite): self
    {
        $key = $prerequisite->field('prerequisite_id') ?? uniqid('prerequisite_', true);
        $this->_prerequisites->add($prerequisite, (string)$key);
        Dev::do('goal.initiative.prerequisite_added', ['initiative' => $this, 'prerequisite' => $prerequisite]);

        return $this;
    }

    public function objectives(): array
    {
        return $this->collectionValues($this->_objectives);
    }

    public function conditions(): array
    {
        return $this->collectionValues($this->_conditions);
    }

    public function criteria(): array
    {
        return array_merge($this->objectives(), $this->conditions());
    }

    public function buildProjections(): array
    {
        $projections = [];

        foreach ($this->criteria() as $criterion) {
            if ($criterion instanceof Criterion && $criterion->satisfied()) {
                continue;
            }

            $satisfied = $criterion instanceof InitiativeObject
                ? (bool)$criterion->field('is_satisfied')
                : false;
            if ($satisfied) {
                continue;
            }

            $tags = $criterion instanceof InitiativeObject ? $criterion->field('tags') : null;
            if (!is_array($tags) || empty($tags)) {
                $tags = [$this->criterionKey($criterion)];
            }

            $priority = $criterion instanceof InitiativeObject ? (float)($criterion->field('priority') ?? 0.0) : 0.0;
            $ttl = $criterion instanceof InitiativeObject ? $criterion->field('ttl') : null;
            $ttl = is_numeric($ttl) ? (float)$ttl : (float)($this->field('ttl') ?? 60.0);

            $context = $this->buildProjectionContext($criterion);

            $projections[] = new Projection([
                'tags' => $tags,
                'priority' => $priority,
                'ttl' => $ttl,
                'context' => $context,
                'source' => 'initiative',
            ]);
        }

        Dev::do('goal.initiative.projections_built', [
            'initiative' => $this,
            'count' => count($projections),
        ]);

        return $projections;
    }

    protected function buildProjectionContext($criterion): Context
    {
        $context = new Context();
        $context->set('initiative', $this->field('name'));
        $context->set('initiative_id', $this->field('initiative_id'));
        $context->set('criterion_key', $this->criterionKey($criterion));

        if ($criterion instanceof InitiativeObject) {
            $context->set('criterion_type', $criterion->field('type'));
            $context->set('criterion_operator', $criterion->field('operator'));
            $context->set('criterion_value', $criterion->field('value'));
        }

        return $context;
    }

    protected function criterionKey($criterion): string
    {
        if ($criterion instanceof Criterion) {
            return $criterion->key();
        }

        if ($criterion instanceof InitiativeObject) {
            $type = (string)$criterion->field('type');
            $operator = (string)$criterion->field('operator');
            $value = (string)$criterion->field('value');
            return strtolower(trim($type . '_' . $operator . '_' . $value));
        }

        return uniqid('criterion_', true);
    }

    protected function collectionValues(OrganizedCollection $collection): array
    {
        $values = [];
        foreach ($collection->contents() as $entry) {
            $values[] = $entry['value'] ?? $entry;
        }
        return $values;
    }
}
