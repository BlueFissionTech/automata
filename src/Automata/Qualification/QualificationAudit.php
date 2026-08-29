<?php

namespace BlueFission\Automata\Qualification;

use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Obj;

class QualificationAudit extends Obj
{
    public function __construct(array $data = [])
    {
        parent::__construct();
        $this->assign(ToolDefinition::mergeConfig([
            'trace_id' => '',
            'correlation_id' => '',
            'subject_id' => '',
            'evaluator' => 'system',
            'rule_version' => '',
            'evaluated_at' => gmdate(DATE_ATOM),
            'reasons' => [],
            'evidence' => [],
            'review' => [],
            'metadata' => [],
        ], $data));
    }
}
