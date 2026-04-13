<?php

namespace BlueFission\Automata\Goal;

use BlueFission\Prototypes\HasConditions;
use BlueFission\Prototypes\Proto;

class Condition extends InitiativeObject
{
    use Proto {
        explain as protected prototypeExplain;
        snapshot as protected prototypeSnapshot;
    }
    use HasConditions {
        normalizeConditionRecord as protected prototypeNormalizeConditionRecord;
        prototypeEvaluateConditionRecord as protected prototypeEvaluateCondition;
    }

    public function snapshot(): array
    {
        $normalized = $this->prototypeNormalizeConditionRecord([
            'name' => $this->field('name') ?? $this->field('path') ?? $this->field('attribute') ?? 'condition',
            'path' => $this->field('path') ?? $this->field('attribute'),
            'expected' => $this->field('expected') ?? $this->field('value'),
            'value' => $this->field('value'),
            'operator' => $this->field('operator') ?? 'eq',
            'weight' => $this->field('weight') ?? 1,
            'meta' => $this->field('meta') ?? [],
            'confidence' => $this->field('confidence') ?? 1.0,
        ]);

        $snapshot = $this->prototypeSnapshot();
        $snapshot['kind'] = 'condition';
        $snapshot['name'] = (string)$normalized['name'];
        $snapshot['path'] = $normalized['path'] ?? null;
        $snapshot['operator'] = $normalized['operator'] ?? 'eq';
        $snapshot['expected'] = $normalized['expected'] ?? null;
        $snapshot['value'] = $snapshot['expected'];
        $snapshot['weight'] = $this->field('weight') ?? 1;
        $snapshot['meta'] = $this->field('meta') ?? [];
        $snapshot['confidence'] = (float)($normalized['confidence'] ?? 1.0);

        return $snapshot;
    }

    public function matches(array $context): bool
    {
        $record = $this->snapshot();

        return $this->prototypeEvaluateCondition([
            'name' => $record['name'],
            'path' => $record['path'],
            'expected' => $record['expected'],
            'operator' => $record['operator'],
            'confidence' => $record['confidence'],
        ], $context);
    }

    public function explain(): string
    {
        $record = $this->snapshot();
        $summary = sprintf(
            'condition[%s] %s %s',
            $record['name'],
            $record['path'] ?? '(self)',
            $record['operator']
        );

        $this->summary($summary);

        return $summary;
    }
}
