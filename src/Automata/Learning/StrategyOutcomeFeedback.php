<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Security\Hash;
use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Automata\Intelligence;
use InvalidArgumentException;
use LogicException;
use Throwable;

/** Explicitly admitted outcome evidence updates advisory scores, never authority. */
final class StrategyOutcomeFeedback
{
    private const METRICS = ['accuracy', 'prediction_accuracy', 'score', 'confidence', 'latency_ms', 'cost', 'energy'];
    private const RATIOS = ['accuracy', 'prediction_accuracy', 'score', 'confidence'];
    private array $entries = [];

    public function __construct(private readonly Intelligence $learner) {}

    /** Returns false for an identical, already applied outcome in this recorder. */
    public function apply(Experience $experience, string $outcomeId): bool
    {
        RecordSnapshot::identifier($outcomeId, 'outcome id');
        $matches = Arr::make($experience->outcomes())
            ->filter(static fn (Outcome $outcome): bool => $outcome->id() === $outcomeId)
            ->values()->val();
        if (Arr::isEmpty($matches)) {
            throw new InvalidArgumentException('Feedback must reference an outcome attached to the experience.');
        }
        $outcome = $matches[0]->toArray();
        $attribution = $outcome['attribution'];
        $strategyId = RecordSnapshot::identifier($attribution['strategy_id'] ?? null, 'strategy id');
        $strategyVersion = RecordSnapshot::identifier($attribution['strategy_version'] ?? null, 'strategy version');
        $context = RecordSnapshot::identifier($attribution['context_key'] ?? null, 'context key');
        $context = Str::make($context)->trim()->val();
        // Intelligence's existing key is id@version. Do not admit ambiguous pairs.
        if (Str::make($strategyId)->contains('@') || Str::make($strategyVersion)->contains('@')) {
            throw new InvalidArgumentException('Strategy identity components cannot contain the @ separator.');
        }
        $observations = $outcome['observations'];
        $metrics = Arr::hasKey($observations, 'feedback') ? $observations['feedback'] : [];
        if (!Arr::is($metrics)) {
            throw new InvalidArgumentException('Outcome feedback must be a metric map.');
        }
        foreach ($metrics as $metric => $value) {
            if (!Arr::has(self::METRICS, $metric, true)) {
                throw new InvalidArgumentException('Unsupported strategy feedback metric: ' . $metric);
            }
            if ($value === null) { continue; }
            if ((!Num::isInt($value) && !Num::isFloat($value))
                || !Num::check($value, 'is_finite') || $value < 0
                || (Arr::has(self::RATIOS, $metric, true) && $value > 1)) {
                throw new InvalidArgumentException('Feedback metrics must be finite numbers within their declared bounds.');
            }
        }
        $feedback = ['successful' => $outcome['successful'],
            ...Arr::make($metrics)->filter(static fn ($value): bool => $value !== null)->val()];
        // Snapshot-only values; the tuple cannot alias concatenated component ids.
        $key = Hash::value(serialize([$experience->id(), $outcomeId]), 'sha256');
        if (isset($this->entries[$key])) {
            if ($this->entries[$key]['outcome'] !== $outcome) {
                throw new InvalidArgumentException('Feedback identity already has different outcome evidence.');
            }
            if ($this->entries[$key]['receipt']['status'] !== 'applied') {
                throw new LogicException('Prior feedback application is uncertain; reconcile before retrying.');
            }
            return false;
        }
        $this->entries[$key] = ['outcome' => $outcome, 'receipt' => [
            'schema_version' => 1, 'experience_id' => $experience->id(), 'outcome_id' => $outcomeId,
            'source' => $outcome['source'], 'trace_id' => $experience->toArray()['trace_id'] ?? null,
            'strategy_id' => $strategyId, 'strategy_version' => $strategyVersion, 'context_key' => $context,
            'feedback' => $feedback, 'status' => 'applying',
        ]];
        try {
            $this->learner->recordStrategyFeedback($strategyId, $strategyVersion, $feedback, $context);
        } catch (Throwable $error) {
            // The learner may have written before throwing. Never blindly repeat it.
            $this->entries[$key]['receipt']['status'] = 'uncertain';
            throw $error;
        }
        $this->entries[$key]['receipt']['status'] = 'applied';
        return true;
    }

    /** Data-only diagnostics; not a durable learner checkpoint or restoration API. */
    public function receipts(): array
    {
        return Arr::make($this->entries)->map(static fn (array $entry): array => $entry['receipt'])
            ->values()->val();
    }
}
