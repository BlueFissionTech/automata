<?php

namespace BlueFission\Automata\Language\Claims;

use BlueFission\Arr;
use BlueFission\Str;

class ClaimEnvelope extends ClaimValue
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);

        $claimId = (string)$this->field('claim_id');
        if ($claimId === '') {
            $claimId = 'claim_' . Str::uuid4();
            $this->field('claim_id', $claimId);
        }

        foreach (['correlation_id', 'trace_id'] as $field) {
            if ((string)$this->field($field) === '') {
                $this->field($field, $claimId);
            }
        }
    }

    public function id(): string
    {
        return (string)$this->field('claim_id');
    }

    public function sourceForm(): string
    {
        return (string)$this->field('source_form');
    }

    public function payload(): mixed
    {
        return $this->field('payload');
    }

    public function sourceContext(): array
    {
        return Arr::make($this->field('source_context') ?? [])->toArray();
    }

    public function policy(): array
    {
        return Arr::make($this->field('policy') ?? [])->toArray();
    }

    protected function defaults(): array
    {
        return [
            'contract_version' => '1.0',
            'claim_id' => '',
            'source_form' => ClaimSourceForm::RAW,
            'payload' => null,
            'source_context' => [],
            'correlation_id' => '',
            'causation_id' => '',
            'trace_id' => '',
            'policy' => [],
            'metadata' => [],
        ];
    }
}
