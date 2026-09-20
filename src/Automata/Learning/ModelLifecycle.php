<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;
use LogicException;

/** Single-writer reference activation; hosts own immutable models and durable deployment. */
final class ModelLifecycle
{
    private array $models;
    private array $activeStack;
    private array $requests = [];
    private int $revision = 0;
    private bool $busy = false;

    public function __construct(
        ModelCandidate $initial,
        private readonly ClassificationEvaluator $evaluator = new ClassificationEvaluator(),
        private readonly int $maximumRequests = 1000
    ) {
        if ($maximumRequests < 1) {
            throw new InvalidArgumentException('Request retention must be positive.');
        }
        $this->models = [$initial];
        $this->activeStack = [$initial];
    }

    public function active(): ModelCandidate { return $this->activeStack[Arr::count($this->activeStack) - 1]; }
    public function revision(): int { return $this->revision; }
    public function receipts(): array
    {
        return Arr::make($this->requests)->map(static fn (array $entry): array => $entry['receipt'])->values()->val();
    }

    /** Callbacks must be trusted, synchronous and side-effect-free. Only explicit approval activates. */
    public function promote(
        string $requestId,
        int $expectedRevision,
        ModelCandidate $candidate,
        TrainingBatch $holdout,
        callable $authorize
    ): array {
        $binding = ['operation' => 'promote', 'expected_revision' => $expectedRevision,
            'candidate' => $candidate, 'holdout' => RecordSnapshot::fingerprint($holdout->toArray())];
        if (($receipt = $this->begin($requestId, $binding)) !== null) { return $receipt; }
        $this->assertModel($candidate);
        $this->busy = true;
        try {
            $report = $this->evaluator->compare($this->active(), $candidate, $holdout);
            $request = $this->request($requestId, 'promote', $candidate, $report);
            $decision = $report['recommended'] ? $this->authorize($authorize, $request) : null;
            $reason = !$report['recommended'] ? 'evaluation_rejected'
                : ($decision['status'] === GovernanceDecision::STATUS_APPROVED ? null : 'governance_not_approved');
            return $this->finish($requestId, $binding, $request, $decision, $reason, $candidate);
        } finally {
            $this->busy = false;
        }
    }

    /** Return to the immediately previous activation; this does not erase historical receipts. */
    public function rollback(string $requestId, int $expectedRevision, callable $authorize): array
    {
        $binding = ['operation' => 'rollback', 'expected_revision' => $expectedRevision];
        if (($receipt = $this->begin($requestId, $binding)) !== null) { return $receipt; }
        $this->busy = true;
        try {
            $count = Arr::count($this->activeStack);
            $previous = $count > 1 ? $this->activeStack[$count - 2] : null;
            $request = $this->request($requestId, 'rollback', $previous, null);
            $decision = $previous !== null ? $this->authorize($authorize, $request) : null;
            $reason = $previous === null ? 'no_previous_model'
                : ($decision['status'] === GovernanceDecision::STATUS_APPROVED ? null : 'governance_not_approved');
            return $this->finish($requestId, $binding, $request, $decision, $reason, $previous);
        } finally {
            $this->busy = false;
        }
    }

    private function begin(string $id, array $binding): ?array
    {
        if ($this->busy) { throw new LogicException('Reentrant lifecycle mutation is not allowed.'); }
        RecordSnapshot::identifier($id, 'request id');
        if ($binding['expected_revision'] < 0) { throw new InvalidArgumentException('Revision cannot be negative.'); }
        if (isset($this->requests[$id])) {
            if ($this->requests[$id]['binding'] !== $binding) {
                throw new LogicException('Request id conflicts with its recorded operation, model or evidence.');
            }
            return $this->requests[$id]['receipt'];
        }
        if ($binding['expected_revision'] !== $this->revision) { throw new LogicException('Stale lifecycle revision.'); }
        if (Arr::count($this->requests) >= $this->maximumRequests || $this->revision === PHP_INT_MAX) {
            throw new LogicException('Lifecycle retention or revision capacity is exhausted.');
        }
        return null;
    }

    private function assertModel(ModelCandidate $candidate): void
    {
        foreach ($this->models as $known) {
            if ($known === $candidate) { return; }
            if ($known->identity() === $candidate->identity()) {
                throw new InvalidArgumentException('An exact model version is already bound to a different candidate.');
            }
            if ($known->strategy() === $candidate->strategy()) {
                throw new InvalidArgumentException('A registered strategy instance cannot be relabelled.');
            }
        }
    }

    private function request(string $id, string $operation, ?ModelCandidate $target, ?array $report): array
    {
        return ['schema_version' => 1, 'request_id' => $id, 'operation' => $operation,
            'expected_revision' => $this->revision, 'from' => $this->active()->identity(),
            'to' => $target?->identity(), 'evaluation' => $report];
    }

    private function authorize(callable $authorize, array $request): array
    {
        $decision = $authorize(RecordSnapshot::copy($request));
        if (!$decision instanceof GovernanceDecision) {
            throw new InvalidArgumentException('Authorization must return an explicit GovernanceDecision.');
        }
        // Read the same status that controls activation; filtered toArray() is not authority.
        return RecordSnapshot::copy(['status' => $decision->status(), 'message' => $decision->message(),
            'payload' => $decision->payload()]);
    }

    private function finish(
        string $id,
        array $binding,
        array $request,
        ?array $decision,
        ?string $reason,
        ?ModelCandidate $target
    ): array {
        $applied = $reason === null;
        $receipt = RecordSnapshot::copy([...$request, 'decision' => $decision, 'applied' => $applied,
            'reason' => $reason, 'revision' => $this->revision + (int) $applied]);
        // All fallible user code and snapshot validation precede the local transition.
        if ($binding['operation'] === 'promote' && !Arr::has($this->models, $target, true)) {
            $this->models[] = $target;
        }
        if ($applied) {
            if ($binding['operation'] === 'promote') { $this->activeStack[] = $target; }
            else { array_pop($this->activeStack); }
            ++$this->revision;
        }
        $this->requests[$id] = ['binding' => $binding, 'receipt' => $receipt];
        return $receipt;
    }
}
