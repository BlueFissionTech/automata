<?php

namespace BlueFission\Automata\Feedback;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;
use BlueFission\Num;

class ReviewRecord extends FeedbackItem
{
    public const STATUS_RECORDED = 'recorded';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';
    public const STATUS_CORRECTED = 'corrected';
    public const STATUS_TRAINING_SIGNAL = 'training_signal';

    public function __construct(array $data = [])
    {
        $data['status'] = $data['status'] ?? self::STATUS_RECORDED;
        $data['actor'] = $data['actor'] ?? 'system';
        $data['reason'] = $data['reason'] ?? '';
        $data['confidence'] = $data['confidence'] ?? 1.0;
        $data['trace'] = $data['trace'] ?? [];
        $data['evidence'] = $data['evidence'] ?? [];
        $data['policy_strategy'] = $data['policy_strategy'] ?? 'external';

        parent::__construct($data);

        Dev::do('feedback.review_record.created', ['record' => $this]);
    }

    public static function correction(
        mixed $originalValue,
        mixed $correctedValue,
        string $actor,
        string $reason = '',
        float $confidence = 1.0,
        array $data = []
    ): self {
        return new self([
            'status' => self::STATUS_CORRECTED,
            'original_value' => $originalValue,
            'corrected_value' => $correctedValue,
            'actor' => $actor,
            'reason' => $reason,
            'confidence' => $confidence,
        ] + $data);
    }

    public static function trainingSignal(string $actor, FeedbackSignal $signal, array $data = []): self
    {
        $evidence = Arr::make($data['evidence'] ?? [])->toArray();

        return new self([
            'status' => self::STATUS_TRAINING_SIGNAL,
            'actor' => $actor,
            'confidence' => Num::min(1.0, Num::max(0.0, abs($signal->value()))),
            'evidence' => [
                'signal_value' => $signal->value(),
            ] + $evidence,
        ] + $data);
    }

    public function status(): string
    {
        return (string)$this->field('status');
    }

    public function originalValue(): mixed
    {
        return $this->field('original_value');
    }

    public function correctedValue(): mixed
    {
        return $this->field('corrected_value');
    }

    public function actor(): string
    {
        return (string)$this->field('actor');
    }

    public function reason(): string
    {
        return (string)$this->field('reason');
    }

    public function confidence(): float
    {
        $confidence = $this->field('confidence');
        $confidence = Num::isValid($confidence) ? (float)$confidence : 0.0;

        return Num::min(1.0, Num::max(0.0, $confidence));
    }

    public function trace(): array
    {
        return Arr::make($this->field('trace') ?? [])->toArray();
    }

    public function evidence(): array
    {
        return Arr::make($this->field('evidence') ?? [])->toArray();
    }

    public function policyStrategy(): string
    {
        return (string)$this->field('policy_strategy');
    }

    public function hasCorrection(): bool
    {
        return $this->field('corrected_value') !== null;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status(),
            'original_value' => $this->originalValue(),
            'corrected_value' => $this->correctedValue(),
            'actor' => $this->actor(),
            'reason' => $this->reason(),
            'confidence' => $this->confidence(),
            'timestamp' => $this->timestamp(),
            'trace' => $this->trace(),
            'evidence' => $this->evidence(),
            'policy_strategy' => $this->policyStrategy(),
            'tags' => $this->tags(),
            'context' => $this->context()->all(),
        ];
    }
}
