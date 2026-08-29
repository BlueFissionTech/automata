<?php

namespace BlueFission\Automata\Qualification;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Obj;

class QualificationResult extends Obj
{
    public function __construct(array $data = [])
    {
        $score = $data['score'] ?? [];
        $plan = $data['follow_up_plan'] ?? null;
        $audit = $data['audit'] ?? [];
        $suggestions = array_map(
            static fn (mixed $suggestion): array => $suggestion instanceof NurtureSuggestion
                ? $suggestion->toArray()
                : Arr::make($suggestion)->toArray(),
            Arr::make($data['suggestions'] ?? [])->toArray()
        );
        $data['score'] = $score instanceof QualificationScore ? $score->toArray() : Arr::make($score)->toArray();
        $data['suggestions'] = $suggestions;
        $data['follow_up_plan'] = $plan instanceof FollowUpPlan ? $plan->toArray() : $plan;
        $data['audit'] = $audit instanceof QualificationAudit ? $audit->toArray() : Arr::make($audit)->toArray();

        parent::__construct();
        $this->assign(ToolDefinition::mergeConfig([
            'score' => [],
            'suggestions' => [],
            'follow_up_plan' => null,
            'audit' => [],
            'metadata' => [],
        ], $data));
    }
}
