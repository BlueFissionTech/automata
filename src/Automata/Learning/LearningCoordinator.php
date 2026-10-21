<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Num;
use BlueFission\DevElation as Dev;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Strategy\IStrategy;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Coordinates synchronous candidate training without activating the result.
 * Hosts own resource limits, model isolation, and durable recovery.
 */
final class LearningCoordinator
{
    private array $requests = [];
    private array $eventErrors = [];
    private array $versions;
    private array $strategies;
    private TrainingBatch $learned;
    private bool $busy = false;
    private bool $uncertain = false;

    public function __construct(
        private readonly ModelCandidate $initial,
        private readonly TrainingPolicy $policy = new TrainingPolicy(),
        private readonly int $maximumRequests = 1000,
        private readonly int $maximumEvidence = 10000
    ) {
        if ($maximumRequests < 1
            || $maximumEvidence < 1
            || Arr::size($initial->training()->samples()) > $maximumEvidence) {
            throw new InvalidArgumentException('Training retention bounds must be positive and hold the initial evidence.');
        }
        $this->learned = $initial->training();
        $this->versions = [$initial->identity()['version'] => true];
        $this->strategies = [$initial->strategy()];
    }

    public function eventErrors(): array
    {
        return $this->eventErrors;
    }

    public function results(): array
    {
        return Arr::make($this->requests)
            ->map(static fn (array $request): TrainingResult => $request['result'])
            ->values()
            ->val();
    }

    /**
     * All successfully admitted training lineage, including the configured anchor.
     * Use evidence()->merge($newLabels) explicitly when fitting a replacement model
     * that should rehearse prior examples. Rewards are not converted into labels.
     */
    public function evidence(): TrainingBatch { return $this->learned; }

    /**
     * Export quiescent data-only state. Models are represented by exact identities
     * and training provenance; the host persists/authenticates their actual artifacts.
     * This checksum detects corruption, not a malicious writer or model substitution.
     */
    public function checkpoint(): array
    {
        if ($this->busy) { throw new LogicException('Cannot checkpoint an in-flight training operation.'); }
        $record = ['schema_version' => 1, 'configuration' => $this->configuration(),
            'requests' => Arr::make($this->requests)->map(static fn (array $entry): array => [
                'request' => $entry['request'], 'result' => $entry['result']->toArray(),
            ])->values()->val(), 'event_errors' => $this->eventErrors];
        return [...$record, 'fingerprint' => RecordSnapshot::fingerprint($record)];
    }

    /**
     * Build an isolated coordinator without retraining, reauthorizing or emitting
     * training hooks. A trusted host loader receives (identity, training batch)
     * and returns an independently restored IStrategy. It must validate artifact
     * integrity, backend compatibility and scope before decoding model bytes.
     * No candidate is activated here; uncertain work stays blocked for reconciliation.
     */
    public function restore(array $checkpoint, callable $load): self
    {
        if ($this->busy) { throw new LogicException('Cannot restore during a training operation.'); }
        $record = RecordSnapshot::copy($checkpoint);
        $fingerprint = $record['fingerprint'] ?? null;
        unset($record['fingerprint']);
        self::keys($record, ['schema_version', 'configuration', 'requests', 'event_errors']);
        if (($record['schema_version'] ?? null) !== 1
            || ($record['configuration'] ?? null) !== $this->configuration()
            || $fingerprint !== RecordSnapshot::fingerprint($record)
            || !Arr::is($record['requests'] ?? null) || !Arr::check($record['requests'], 'array_is_list')
            || Arr::size($record['requests']) > $this->maximumRequests
            || !Arr::is($record['event_errors'] ?? null) || !Arr::check($record['event_errors'], 'array_is_list')) {
            throw new InvalidArgumentException('Checkpoint schema, configuration or integrity mismatch.');
        }

        // Validate the entire history before any model loader is allowed to run.
        // Scores/pressure were observed at training time; do not re-run mutable hooks.
        $validated = [];
        $versions = [$this->initial->identity()['version'] => true];
        $learned = $this->initial->training();
        $uncertain = false;
        foreach ($record['requests'] as $entry) {
            if (!Arr::is($entry)) { throw new InvalidArgumentException('Malformed checkpoint entry.'); }
            self::keys($entry, ['request', 'result']);
            $request = $entry['request'] ?? null;
            $result = $entry['result'] ?? null;
            if (!Arr::is($request) || !Arr::is($result) || $uncertain) { throw new InvalidArgumentException('Invalid training history.'); }
            self::keys($request, ['version', 'batch', 'trigger']);
            $version = RecordSnapshot::identifier($request['version'] ?? null, 'candidate version');
            if (!Arr::is($request['batch'] ?? null) || !Arr::is($request['trigger'] ?? null)) {
                throw new InvalidArgumentException('Missing training input record.');
            }
            $batch = TrainingBatch::fromArray($request['batch']);
            $trigger = $request['trigger'];
            self::keys($trigger, ['signals', 'corrected_outcomes', 'operator_requested']);
            if (!Arr::is($trigger['signals'] ?? null) || !Arr::is($trigger['corrected_outcomes'] ?? null)
                || !Flag::isBool($trigger['operator_requested'] ?? null)) {
                throw new InvalidArgumentException('Malformed training trigger.');
            }
            $normalized = new TrainingTrigger($trigger['signals'], $trigger['corrected_outcomes'], $trigger['operator_requested']);
            if ($normalized->toArray() !== $trigger) { throw new InvalidArgumentException('Noncanonical trigger.'); }
            $id = self::validateResult($result, $version, $this->initial->identity()['id'], $batch);
            if (isset($validated[$id]) || isset($versions[$version])) { throw new InvalidArgumentException('Repeated training identity.'); }
            $this->validateAssessment($result['assessment'], $batch, $learned, $trigger);
            // Projection and immutable lineage must match even for deferred/denied work.
            $merged = $learned->merge($batch);
            if ($result['status'] === 'trained') {
                $learned = $merged;
                if (Arr::size($learned->samples()) > $this->maximumEvidence) {
                    throw new InvalidArgumentException('Checkpoint evidence exceeds retention.');
                }
            }
            if (Arr::has(['trained', 'uncertain'], $result['status'], true)) { $versions[$version] = true; }
            $uncertain = $result['status'] === 'uncertain';
            $validated[$id] = ['request' => $request, 'result' => $result, 'batch' => $batch];
        }
        foreach ($record['event_errors'] as $error) {
            if (!Arr::is($error)) { throw new InvalidArgumentException('Malformed event error.'); }
            self::keys($error, ['request_id', 'event', 'error_type']);
            $requestId = RecordSnapshot::identifier($error['request_id'] ?? null, 'event request id');
            $event = RecordSnapshot::identifier($error['event'] ?? null, 'event');
            // A requested-event observer can fail before authorization throws.
            // That diagnostic is real even though no result/version was committed.
            if (!isset($validated[$requestId]) && $event !== 'requested') {
                throw new InvalidArgumentException('Unknown event error request.');
            }
            RecordSnapshot::identifier($error['error_type'] ?? null, 'error type');
        }

        $models = $this->strategies;
        $loadCandidate = static function (array $identity, TrainingBatch $batch) use ($load, &$models): ModelCandidate {
            $model = $load($identity, $batch);
            if (!$model instanceof IStrategy || Arr::has($models, $model, true)) {
                throw new InvalidArgumentException('Loader must return independent restored strategies.');
            }
            $models[] = $model;
            return new ModelCandidate($identity['id'], $identity['version'], $model, $batch);
        };
        $this->busy = true;
        try {
            $initial = $loadCandidate($this->initial->identity(), $this->initial->training());
            $restored = new self($initial, $this->policy, $this->maximumRequests, $this->maximumEvidence);
            foreach ($validated as $id => $entry) {
                $result = $entry['result'];
                $candidate = $result['status'] === 'trained' ? $loadCandidate($result['candidate'], $entry['batch']) : null;
                if ($candidate !== null) { $restored->strategies[] = $candidate->strategy(); }
                $restored->requests[$id] = ['binding' => RecordSnapshot::fingerprint($entry['request']),
                    'request' => $entry['request'], 'result' => new TrainingResult($result, $candidate)];
            }
            $restored->learned = $learned;
            $restored->versions = $versions;
            $restored->uncertain = $uncertain;
            $restored->eventErrors = $record['event_errors'];
            return $restored;
        } finally {
            $this->busy = false;
        }
    }

    /** Bind restoration to an exact anchor, projection, pressure policy and bounds. */
    private function configuration(): array
    {
        return ['initial' => ['identity' => $this->initial->identity(), 'training' => $this->initial->training()->toArray()],
            'policy' => $this->policy->toArray(), 'maximum_requests' => $this->maximumRequests, 'maximum_evidence' => $this->maximumEvidence];
    }

    /** Check receipt relationships without executing filters, fitting or authorization. */
    private static function validateResult(array $result, string $version, string $modelId, TrainingBatch $batch): string
    {
        self::keys($result, ['schema_version', 'request_id', 'candidate', 'training_fingerprint', 'assessment', 'decision',
            'status', 'failure', 'elapsed_ms', 'cost', 'energy']);
        $id = RecordSnapshot::identifier($result['request_id'] ?? null, 'request id');
        $status = $result['status'] ?? null;
        if (($result['schema_version'] ?? null) !== 1 || !Arr::has(['deferred', 'denied', 'trained', 'uncertain'], $status, true)
            || ($result['candidate'] ?? null) !== ['id' => $modelId, 'version' => $version]
            || ($result['training_fingerprint'] ?? null) !== RecordSnapshot::fingerprint($batch->toArray())
            || !Arr::is($result['assessment'] ?? null)
            || ($result['assessment']['eligible'] ?? null) !== ($status !== 'deferred')) {
            throw new InvalidArgumentException('Checkpoint training receipt does not match its request.');
        }
        $decision = $result['decision'] ?? null;
        if (($status === 'deferred' && $decision !== null) || ($status !== 'deferred' && !Arr::is($decision))
            || (Arr::has(['trained', 'uncertain'], $status, true) && ($decision['status'] ?? null) !== GovernanceDecision::STATUS_APPROVED)
            || ($status === 'denied' && ($decision['status'] ?? null) === GovernanceDecision::STATUS_APPROVED)) {
            throw new InvalidArgumentException('Checkpoint training decision is inconsistent.');
        }
        if ($status === 'uncertain') { RecordSnapshot::identifier($result['failure'] ?? null, 'failure'); }
        elseif (($result['failure'] ?? null) !== null) { throw new InvalidArgumentException('Unexpected training failure.'); }
        if (Arr::has(['trained', 'uncertain'], $status, true)) { RecordSnapshot::number($result['elapsed_ms'] ?? null, 'elapsed time'); }
        elseif (($result['elapsed_ms'] ?? null) !== null) { throw new InvalidArgumentException('Unstarted training has elapsed time.'); }
        return $id;
    }

    /** Recompute lineage/count invariants while retaining the historical filtered pressure. */
    private function validateAssessment(array $assessment, TrainingBatch $batch, TrainingBatch $learned, array $trigger): void
    {
        $policy = $this->policy->toArray();
        foreach ($policy as $field => $value) {
            if (($assessment[$field] ?? null) !== $value) { throw new InvalidArgumentException('Assessment policy mismatch.'); }
        }
        $known = [];
        foreach ($learned->toArray()['examples'] as $row) { $known[RecordSnapshot::fingerprint([$row['experience_id'], $row['outcome_id']])] = true; }
        $seen = $new = [];
        foreach ($batch->toArray()['examples'] as $row) {
            $key = RecordSnapshot::fingerprint([$row['experience_id'], $row['outcome_id']]);
            if (isset($seen[$key])) { throw new InvalidArgumentException('Duplicate checkpoint training lineage.'); }
            $seen[$key] = true;
            if (!isset($known[$key])) { $new[$key] = true; }
        }
        foreach ($trigger['corrected_outcomes'] as $reference) {
            if (!isset($new[RecordSnapshot::fingerprint([$reference['experience_id'], $reference['outcome_id']])])) {
                throw new InvalidArgumentException('Correction does not cite new evidence.');
            }
        }
        $pressure = RecordSnapshot::number($assessment['pressure'] ?? null, 'recorded pressure');
        $count = Arr::size($seen);
        $enough = $count >= $policy['minimum_examples'];
        $eligible = $enough && ($trigger['operator_requested'] || $pressure >= $policy['minimum_pressure']);
        $reason = !$enough ? 'insufficient_examples' : ($eligible ? null : 'insufficient_pressure');
        if ($count > $policy['maximum_examples'] || ($assessment['examples'] ?? null) !== $count
            || ($assessment['new_examples'] ?? null) !== Arr::size($new)
            || ($assessment['corrected_examples'] ?? null) !== Arr::size($trigger['corrected_outcomes'])
            || ($assessment['trigger'] ?? null) !== $trigger || ($assessment['eligible'] ?? null) !== $eligible
            || !Arr::hasKey($assessment, 'reason') || $assessment['reason'] !== $reason) {
            throw new InvalidArgumentException('Checkpoint assessment is inconsistent with its evidence.');
        }
    }

    /** Unknown fields must not silently acquire semantics on import. */
    private static function keys(array $record, array $allowed): void
    {
        if (Arr::make($record)->keys()->diff($allowed)->val() !== []) { throw new InvalidArgumentException('Unknown checkpoint field.'); }
    }

    /**
     * Request one candidate training attempt.
     * The host supplies authorization, an independent strategy factory, and a trainer
     * that returns void only after completed training. This method never activates it.
     */
    public function train(
        string $requestId,
        string $candidateVersion,
        TrainingBatch $batch,
        TrainingTrigger $trigger,
        callable $factory,
        callable $trainer,
        callable $authorize
    ): TrainingResult {
        if ($this->busy) {
            throw new LogicException('Reentrant training is not allowed.');
        }

        RecordSnapshot::identifier($requestId, 'training request id');
        RecordSnapshot::identifier($candidateVersion, 'candidate version');
        $request = [
            'version' => $candidateVersion,
            'batch' => $batch->toArray(),
            'trigger' => $trigger->toArray(),
        ];
        $binding = RecordSnapshot::fingerprint($request);
        if (isset($this->requests[$requestId])) {
            if ($this->requests[$requestId]['binding'] !== $binding) {
                throw new LogicException('Conflicting training request identity.');
            }
            return $this->requests[$requestId]['result'];
        }
        if ($this->uncertain) {
            throw new LogicException('Uncertain training requires host reconciliation before more work.');
        }
        if (Arr::size($this->requests) >= $this->maximumRequests) {
            throw new LogicException('Training result retention is exhausted.');
        }
        if (isset($this->versions[$candidateVersion])) {
            throw new LogicException('Candidate version is already reserved.');
        }

        $this->busy = true;
        try {
            $assessment = $this->policy->assess($batch, $this->learned, $trigger);
            $identity = [
                'id' => $this->initial->identity()['id'],
                'version' => $candidateVersion,
            ];
            $record = [
                'schema_version' => 1,
                'request_id' => $requestId,
                'candidate' => $identity,
                'training_fingerprint' => RecordSnapshot::fingerprint($batch->toArray()),
                'assessment' => $assessment,
                'decision' => null,
                'status' => 'deferred',
                'failure' => null,
                'elapsed_ms' => null,
                'cost' => null,
                'energy' => null,
            ];
            $candidate = null;
            if ($assessment['eligible']) {
                $nextEvidence = $this->accumulate($batch);
                $record['status'] = 'requested';
                $this->observe('requested', $record);
                $decision = $authorize(RecordSnapshot::copy($record));
                if (!$decision instanceof GovernanceDecision) {
                    throw new InvalidArgumentException('Training requires an explicit GovernanceDecision.');
                }
                $record['decision'] = RecordSnapshot::copy([
                    'status' => $decision->status(),
                    'message' => $decision->message(),
                    'payload' => $decision->payload(),
                ]);
                $record['status'] = 'denied';
                if ($record['decision']['status'] === GovernanceDecision::STATUS_APPROVED) {
                    $this->versions[$candidateVersion] = true;
                    $record['status'] = 'started';
                    $this->observe('started', $record);
                    $started = hrtime(true);
                    try {
                        $strategy = $factory();
                        if (!$strategy instanceof IStrategy || Arr::has($this->strategies, $strategy, true)) {
                            throw new InvalidArgumentException('Factory must create an independent strategy instance.');
                        }
                        $this->strategies[] = $strategy;
                        if ($trainer($strategy, $batch) !== null) {
                            throw new InvalidArgumentException('Trainer must return void on success; non-void results are uncertain.');
                        }
                        $candidate = new ModelCandidate($identity['id'], $identity['version'], $strategy, $batch);
                        $record['status'] = 'trained';
                        $this->learned = $nextEvidence;
                    } catch (Throwable $error) {
                        $this->uncertain = true;
                        $record['status'] = 'uncertain';
                        $record['failure'] = $error::class;
                    }
                    $record['elapsed_ms'] = Num::make(hrtime(true))->subtract($started)->divide(1000000)->val();
                }
            }
            $result = new TrainingResult($record, $candidate);
            $this->requests[$requestId] = ['binding' => $binding, 'request' => $request, 'result' => $result];
            $event = match ($result->status()) {
                'trained' => 'completed',
                'uncertain' => 'failed',
                default => $result->status(),
            };
            $this->observe($event, $record);
            return $result;
        } finally {
            $this->busy = false;
        }
    }

    private function observe(string $event, array $record): void
    {
        try {
            Dev::do('automata.learning.training.' . $event, [
                RecordSnapshot::copy([...$record, 'event' => $event]),
            ]);
        } catch (Throwable $error) {
            $this->eventErrors[] = [
                'request_id' => $record['request_id'],
                'event' => $event,
                'error_type' => $error::class,
            ];
        }
    }

    /** Keep learned lineage across training windows; dropped rows must not become novel again. */
    private function accumulate(TrainingBatch $batch): TrainingBatch
    {
        $rows = [];
        foreach ([$this->learned, $batch] as $source) {
            foreach ($source->toArray()['examples'] as $row) {
                $key = RecordSnapshot::fingerprint([
                    'experience_id' => $row['experience_id'],
                    'outcome_id' => $row['outcome_id'],
                ]);
                $rows[$key] = new TrainingExample($row['experience_id'], $row['outcome_id'], $row['sample'], $row['label']);
            }
        }
        if (Arr::size($rows) > $this->maximumEvidence) {
            throw new LogicException('Learned evidence retention is exhausted.');
        }
        $projection = $batch->toArray();
        return new TrainingBatch(
            $projection['projection_id'],
            $projection['projection_version'],
            Arr::make($rows)->values()->val()
        );
    }
}
