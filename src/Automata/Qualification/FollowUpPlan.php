<?php

namespace BlueFission\Automata\Qualification;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Obj;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

class FollowUpPlan extends Obj
{
    public function __construct(array $data = [])
    {
        $data = ToolDefinition::mergeConfig([
            'id' => '',
            'subject_id' => '',
            'steps' => [],
            'max_steps' => 3,
            'stop_conditions' => [],
            'expires_at' => null,
            'requires_review' => true,
            'trace' => [],
            'metadata' => [],
        ], $data);
        $maxSteps = (int)$data['max_steps'];
        if ($maxSteps < 1 || Arr::count($data['steps']) > $maxSteps) {
            throw new InvalidArgumentException('Follow-up steps exceed the bounded plan limit.');
        }

        parent::__construct();
        $this->assign($data);
    }

    public function next(array $context = [], ?DateTimeImmutable $now = null): ?array
    {
        $now ??= new DateTimeImmutable();
        if ($this->expired($this->field('expires_at'), $now) || $this->matches($this->field('stop_conditions'), $context)) {
            return null;
        }

        foreach (Arr::make($this->field('steps') ?? [])->toArray() as $step) {
            $step = Arr::make($step)->toArray();
            if ($this->expired($step['expires_at'] ?? null, $now)) {
                continue;
            }
            if ($this->future($step['not_before'] ?? null, $now)) {
                continue;
            }
            if ($this->matches($step['stop_conditions'] ?? [], $context)) {
                return null;
            }

            return $step;
        }

        return null;
    }

    protected function matches(mixed $conditions, array $context): bool
    {
        $conditions = Arr::make($conditions ?? [])->toArray();
        if ($conditions === []) {
            return false;
        }
        foreach ($conditions as $key => $expected) {
            if (!Arr::hasKey($context, $key) || $context[$key] !== $expected) {
                return false;
            }
        }

        return true;
    }

    protected function expired(mixed $value, DateTimeImmutable $now): bool
    {
        if (!$value) {
            return false;
        }
        try {
            return new DateTimeImmutable((string)$value) <= $now;
        } catch (Throwable) {
            return true;
        }
    }

    protected function future(mixed $value, DateTimeImmutable $now): bool
    {
        if (!$value) {
            return false;
        }
        try {
            return new DateTimeImmutable((string)$value) > $now;
        } catch (Throwable) {
            return true;
        }
    }
}
