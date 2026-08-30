<?php

namespace BlueFission\Automata\Qualification;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Num;
use BlueFission\Obj;

class QualificationScore extends Obj
{
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_NURTURE = 'nurture';
    public const STATUS_REVIEW = 'review';
    public const STATUS_DISQUALIFIED = 'disqualified';

    public function __construct(array $data = [])
    {
        parent::__construct();
        $this->assign(ToolDefinition::mergeConfig([
            'subject_id' => '',
            'score' => 0.0,
            'confidence' => 0.0,
            'status' => self::STATUS_REVIEW,
            'reasons' => [],
            'unmet_criteria' => [],
            'evidence' => [],
            'trace' => [],
            'metadata' => [],
        ], $data));
    }

    public function score(): float
    {
        return Num::min(1.0, Num::max(0.0, (float)$this->field('score')));
    }

    public function confidence(): float
    {
        return Num::min(1.0, Num::max(0.0, (float)$this->field('confidence')));
    }

    public function status(): string
    {
        return (string)$this->field('status');
    }

    public function unmetCriteria(): array
    {
        return Arr::make($this->field('unmet_criteria') ?? [])->toArray();
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['score'] = $this->score();
        $data['confidence'] = $this->confidence();

        return $data;
    }
}
