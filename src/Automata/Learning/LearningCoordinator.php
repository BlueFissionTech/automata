<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\DevElation as Dev;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Strategy\IStrategy;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;
use LogicException;
use Throwable;

/** Synchronous candidate training. Hosts own resource limits, isolation and durable recovery. */
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
        if ($maximumRequests < 1 || $maximumEvidence < 1 || Arr::count($initial->training()->samples()) > $maximumEvidence) {
            throw new InvalidArgumentException('Training retention bounds must be positive and hold the initial evidence.');
        }
        $this->learned = $initial->training();
        $this->versions = [$initial->identity()['version'] => true];
        $this->strategies = [$initial->strategy()];
    }

    public function eventErrors(): array { return $this->eventErrors; }

    public function results(): array
    {
        return Arr::make($this->requests)->map(static fn (array $request): TrainingResult => $request['result'])->values()->val();
    }

    /** The factory creates independent state; the trainer returns normally only on completed training. */
    public function train(
        string $requestId,
        string $candidateVersion,
        TrainingBatch $batch,
        TrainingTrigger $trigger,
        callable $factory,
        callable $trainer,
        callable $authorize
    ): TrainingResult {
        if ($this->busy) { throw new LogicException('Reentrant training is not allowed.'); }
        RecordSnapshot::identifier($requestId, 'training request id');
        RecordSnapshot::identifier($candidateVersion, 'candidate version');
        $binding = RecordSnapshot::fingerprint(['version' => $candidateVersion, 'batch' => $batch->toArray(), 'trigger' => $trigger->toArray()]);
        if (isset($this->requests[$requestId])) {
            if ($this->requests[$requestId]['binding'] !== $binding) { throw new LogicException('Conflicting training request identity.'); }
            return $this->requests[$requestId]['result'];
        }
        if ($this->uncertain) { throw new LogicException('Uncertain training requires host reconciliation before more work.'); }
        if (Arr::count($this->requests) >= $this->maximumRequests) { throw new LogicException('Training result retention is exhausted.'); }
        if (isset($this->versions[$candidateVersion])) { throw new LogicException('Candidate version is already reserved.'); }
        $this->busy = true;
        try {
            $assessment = $this->policy->assess($batch, $this->learned, $trigger);
            $identity = ['id' => $this->initial->identity()['id'], 'version' => $candidateVersion];
            $record = ['schema_version' => 1, 'request_id' => $requestId, 'candidate' => $identity,
                'training_fingerprint' => RecordSnapshot::fingerprint($batch->toArray()), 'assessment' => $assessment,
                'decision' => null, 'status' => 'deferred', 'failure' => null, 'elapsed_ms' => null, 'cost' => null, 'energy' => null];
            $candidate = null;
            if ($assessment['eligible']) {
                $nextEvidence = $this->accumulate($batch);
                $record['status'] = 'requested';
                $this->observe('requested', $record);
                $decision = $authorize(RecordSnapshot::copy($record));
                if (!$decision instanceof GovernanceDecision) { throw new InvalidArgumentException('Training requires an explicit GovernanceDecision.'); }
                $record['decision'] = RecordSnapshot::copy(['status' => $decision->status(), 'message' => $decision->message(), 'payload' => $decision->payload()]);
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
            $this->requests[$requestId] = ['binding' => $binding, 'result' => $result];
            $event = match ($result->status()) { 'trained' => 'completed', 'uncertain' => 'failed', default => $result->status() };
            $this->observe($event, $record);
            return $result;
        } finally {
            $this->busy = false;
        }
    }

    private function observe(string $event, array $record): void
    {
        try { Dev::do('automata.learning.training.' . $event, [RecordSnapshot::copy([...$record, 'event' => $event])]); }
        catch (Throwable $error) {
            $this->eventErrors[] = ['request_id' => $record['request_id'], 'event' => $event, 'error_type' => $error::class];
        }
    }

    /** Keep learned lineage across training windows; dropped rows must not become novel again. */
    private function accumulate(TrainingBatch $batch): TrainingBatch
    {
        $rows = [];
        foreach ([$this->learned, $batch] as $source) {
            foreach ($source->toArray()['examples'] as $row) {
                $key = RecordSnapshot::fingerprint(['experience_id' => $row['experience_id'], 'outcome_id' => $row['outcome_id']]);
                $rows[$key] = new TrainingExample($row['experience_id'], $row['outcome_id'], $row['sample'], $row['label']);
            }
        }
        if (Arr::count($rows) > $this->maximumEvidence) { throw new LogicException('Learned evidence retention is exhausted.'); }
        $projection = $batch->toArray();
        return new TrainingBatch($projection['projection_id'], $projection['projection_version'], Arr::make($rows)->values()->val());
    }
}
