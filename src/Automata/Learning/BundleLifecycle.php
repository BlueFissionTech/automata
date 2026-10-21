<?php

namespace BlueFission\Automata\Learning;

use BlueFission\{Arr, Flag, Num, Str};
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Publish an evaluated compatible bundle and its receipt in one store transition.
 *
 * Training, evaluation, activation and execution are separate decisions. Trusted
 * synchronous evaluators/authorizers must have no external side effects. This
 * coordinator never executes an artifact or trains a model. Every operation reads
 * the authoritative store; the store owns atomicity and writer coordination.
 */
final class BundleLifecycle
{
    private bool $busy = false;
    private ?array $uncertain = null;

    /** Bind one scope and bounded receipt-retention policy to persisted state. */
    public function __construct(private readonly IBundleStore $store, private readonly string $scope, private readonly int $maximumRequests = 1000)
    {
        RecordSnapshot::identifier($scope, 'scope');
        if ($maximumRequests < 1) { throw new InvalidArgumentException('Request retention must be positive.'); }
    }

    /** Explicit host configuration creates the anchor only in an empty store. */
    public function initialize(LearningBundle $anchor, callable $readArtifact): void
    {
        $this->enter();
        try {
            if ($this->store->load() !== null) { throw new LogicException('Bundle store is already initialized.'); }
            $this->checkScope($anchor);
            BundleSnapshot::resolve($anchor, $readArtifact);
            $digest = $anchor->digest();
            $state = ['schema_version' => 1, 'scope' => $this->scope, 'maximum_requests' => $this->maximumRequests,
                'anchor' => $digest, 'bundles' => [$digest => $anchor->toArray()], 'stack' => [$digest], 'revision' => 0, 'requests' => []];
            $this->commit(null, $state, ['operation' => 'initialize', 'target' => $digest]);
        } finally { $this->busy = false; }
    }

    /** Read one complete active manifest and capture its verified artifact bytes. */
    public function active(callable $readArtifact): BundleSnapshot
    {
        $this->enter();
        try { return BundleSnapshot::resolve($this->activeBundle($this->state()), $readArtifact); }
        finally { $this->busy = false; }
    }

    /** The revision advances only for accepted activation or rollback transitions. */
    public function revision(): int
    {
        $this->enter();
        try { return $this->state()['revision']; }
        finally { $this->busy = false; }
    }
    /** Retained receipts include rejected transitions and exact evaluation/decision records. */
    public function receipts(): array
    {
        $this->enter();
        try { return Arr::make($this->state()['requests'])->map(static fn ($entry) => $entry['receipt'])->values()->val(); }
        finally { $this->busy = false; }
    }

    /**
     * Evaluate verified snapshots, require explicit approval, then atomically publish.
     * An identical request replays its receipt without resolving, evaluating or
     * authorizing again. New evidence or another attempt requires a new request id.
     */
    public function activate(string $requestId, int $expectedRevision, LearningBundle $candidate, callable $evaluate, callable $authorize, callable $readArtifact): array
    {
        $this->enter();
        try {
            $this->checkScope($candidate);
            $state = $this->state();
            $binding = ['operation' => 'activate', 'expected_revision' => $expectedRevision, 'target' => $candidate->digest()];
            if (($receipt = $this->begin($state, $requestId, $binding)) !== null) { return $receipt; }
            foreach ($state['bundles'] as $record) {
                $known = LearningBundle::fromArray($record);
                if ($known->identity() === $candidate->identity() && $known->digest() !== $candidate->digest()) {
                    throw new InvalidArgumentException('Bundle identity already names different content.');
                }
            }
            $from = BundleSnapshot::resolve($this->activeBundle($state), $readArtifact);
            $to = BundleSnapshot::resolve($candidate, $readArtifact);
            $evaluation = RecordSnapshot::copy($evaluate($from, $to));
            if (!Arr::is($evaluation) || !Flag::isBool($evaluation['accepted'] ?? null) || !Arr::is($evaluation['evidence'] ?? null)) {
                throw new InvalidArgumentException('Bundle evaluation requires an explicit accepted Boolean and evidence record.');
            }
            $request = $this->request($state, $requestId, $binding, $evaluation);
            $decision = $evaluation['accepted'] ? $this->decision($authorize($request)) : null;
            $reason = !$evaluation['accepted'] ? 'evaluation_rejected'
                : ($decision['status'] === GovernanceDecision::STATUS_APPROVED ? null : 'governance_not_approved');
            $next = $state;
            $next['bundles'][$candidate->digest()] = $candidate->toArray();
            if ($reason === null) { $next['stack'][] = $candidate->digest(); ++$next['revision']; }
            return $this->finish($state, $next, $requestId, $binding, $request, $decision, $reason);
        } finally { $this->busy = false; }
    }

    /** Restore the previous complete compatible set; never erase its history or anchor. */
    public function rollback(string $requestId, int $expectedRevision, callable $authorize, callable $readArtifact): array
    {
        $this->enter();
        try {
            $state = $this->state();
            $binding = ['operation' => 'rollback', 'expected_revision' => $expectedRevision];
            if (($receipt = $this->begin($state, $requestId, $binding)) !== null) { return $receipt; }
            $count = Arr::size($state['stack']);
            $target = $count > 1 ? $state['stack'][$count - 2] : null;
            $request = $this->request($state, $requestId, [...$binding, 'target' => $target], null);
            $decision = null;
            if ($target !== null) {
                BundleSnapshot::resolve(LearningBundle::fromArray($state['bundles'][$target]), $readArtifact);
                $decision = $this->decision($authorize($request));
            }
            $reason = $target === null ? 'no_previous_bundle'
                : ($decision['status'] === GovernanceDecision::STATUS_APPROVED ? null : 'governance_not_approved');
            $next = $state;
            if ($reason === null) { $next['stack'] = Arr::make($next['stack'])->slice(0, -1)->val(); ++$next['revision']; }
            return $this->finish($state, $next, $requestId, $binding, $request, $decision, $reason);
        } finally { $this->busy = false; }
    }

    /**
     * Resolve a failed write by reading authoritative state, never by repeating it.
     * A lost acknowledgement may already have published. A pre-publication failure
     * leaves the old state. The store contract forbids writes arriving after this
     * recovery read. Artifact failure keeps the owner uncertain and unusable.
     */
    public function recover(callable $readArtifact): array
    {
        if ($this->busy) { throw new LogicException('Reentrant bundle operation.'); }
        $this->busy = true;
        try {
            $pending = $this->uncertain;
            $raw = $this->store->load();
            if ($raw === null) {
                if (($pending['operation'] ?? null) !== 'initialize') { throw new LogicException('No committed bundle to recover.'); }
                $result = ['status' => 'not_committed', 'revision' => null, 'receipt' => null];
            } else {
                $state = $this->validate($raw);
                BundleSnapshot::resolve($this->activeBundle($state), $readArtifact);
                $entry = isset($pending['request_id']) ? ($state['requests'][$pending['request_id']] ?? null) : null;
                if ($entry !== null && RecordSnapshot::fingerprint($entry) !== $pending['entry_digest']) {
                    throw new LogicException('Another transition owns the uncertain request identity; explicit reconciliation is required.');
                }
                $receipt = $entry['receipt'] ?? null;
                $committed = ($pending['operation'] ?? null) === 'initialize' ? $state['anchor'] === $pending['target'] : $receipt !== null;
                $result = ['status' => $pending === null ? 'ready' : ($committed ? 'committed' : 'not_committed'),
                    'revision' => $state['revision'], 'receipt' => $receipt];
            }
            $this->uncertain = null;
            return $result;
        } finally { $this->busy = false; }
    }

    /** Enforce replay binding and expected activation revision before invoking host code. */
    private function begin(array $state, string $id, array $binding): ?array
    {
        RecordSnapshot::identifier($id, 'request id');
        if ($binding['expected_revision'] < 0) { throw new InvalidArgumentException('Revision cannot be negative.'); }
        if (isset($state['requests'][$id])) {
            if ($state['requests'][$id]['binding'] !== $binding) { throw new LogicException('Request identity conflicts with its retained transition.'); }
            return $state['requests'][$id]['receipt'];
        }
        if ($binding['expected_revision'] !== $state['revision']) { throw new LogicException('Stale activation revision.'); }
        if (Arr::size($state['requests']) >= $this->maximumRequests || $state['revision'] === PHP_INT_MAX) { throw new LogicException('Bundle retention capacity exhausted.'); }
        return null;
    }

    /** Construct the exact transition/evaluation supplied for independent authorization. */
    private function request(array $state, string $id, array $binding, ?array $evaluation): array
    {
        return ['schema_version' => 1, 'scope' => $this->scope, 'request_id' => $id,
            'operation' => $binding['operation'], 'expected_revision' => $binding['expected_revision'],
            'from' => $this->activeBundle($state)->digest(), 'to' => $binding['target'], 'evaluation' => $evaluation];
    }

    /** Preserve the same governance status that actually gates publication. */
    private function decision(mixed $decision): array
    {
        if (!$decision instanceof GovernanceDecision) { throw new InvalidArgumentException('Explicit governance decision required.'); }
        return RecordSnapshot::copy(['status' => $decision->status(), 'message' => $decision->message(), 'payload' => $decision->payload()]);
    }

    /** The receipt and complete active set are committed together, including rejections. */
    private function finish(array $old, array $next, string $id, array $binding, array $request, ?array $decision, ?string $reason): array
    {
        $receipt = [...$request, 'decision' => $decision, 'applied' => $reason === null, 'reason' => $reason, 'revision' => $next['revision']];
        $next['requests'][$id] = ['binding' => $binding, 'receipt' => $receipt];
        $this->commit($old, $next, ['operation' => $binding['operation'], 'request_id' => $id,
            'entry_digest' => RecordSnapshot::fingerprint($next['requests'][$id])]);
        return $receipt;
    }

    /** Any write exception blocks this owner until an explicit authoritative recovery. */
    private function commit(?array $old, array $next, array $attempt): void
    {
        $next = $this->validate($next);
        try { $this->store->compareAndSwap($old === null ? null : RecordSnapshot::fingerprint($old), $next); }
        catch (Throwable $error) { $this->uncertain = $attempt; throw $error; }
    }

    /** Read and validate retained history; imported revisions are never trusted alone. */
    private function state(): array { return $this->validate($this->store->load() ?? throw new LogicException('Initialize the bundle anchor first.')); }

    /** Reconstruct activation order and check every exact manifest/receipt relationship. */
    private function validate(array $state): array
    {
        $state = RecordSnapshot::copy($state);
        if (($state['schema_version'] ?? null) !== 1 || ($state['scope'] ?? null) !== $this->scope
            || ($state['maximum_requests'] ?? null) !== $this->maximumRequests || !Num::isInt($state['revision'] ?? null)
            || !Arr::is($state['bundles'] ?? null) || !Arr::is($state['stack'] ?? null) || !Arr::is($state['requests'] ?? null)
            || !Arr::check($state['stack'], 'array_is_list') || Arr::size($state['requests']) > $this->maximumRequests
            || Arr::make($state)->keys()->diff(['schema_version', 'scope', 'maximum_requests', 'anchor', 'bundles', 'stack', 'revision', 'requests'])->val() !== []) {
            throw new InvalidArgumentException('Invalid bundle lifecycle state or policy.');
        }
        $known = [];
        foreach ($state['bundles'] as $digest => $record) {
            if (!Arr::is($record)) { throw new InvalidArgumentException('Malformed bundle catalogue.'); }
            $bundle = LearningBundle::fromArray($record); $this->checkScope($bundle);
            $identity = RecordSnapshot::fingerprint($bundle->identity());
            if ($digest !== $bundle->digest() || isset($known[$identity])) { throw new InvalidArgumentException('Conflicting bundle catalogue identity.'); }
            $known[$identity] = true;
        }
        $anchor = $state['anchor'] ?? null;
        if (!Str::is($anchor) || !isset($state['bundles'][$anchor])) { throw new InvalidArgumentException('Missing retained anchor.'); }
        $stack = [$anchor]; $revision = 0;
        foreach ($state['requests'] as $id => $entry) {
            if (!Arr::is($entry) || !Arr::is($entry['binding'] ?? null) || !Arr::is($entry['receipt'] ?? null)) { throw new InvalidArgumentException('Malformed activation receipt.'); }
            $binding = $entry['binding']; $receipt = $entry['receipt'];
            $operation = $binding['operation'] ?? null;
            $from = $stack[Arr::size($stack) - 1];
            $target = $operation === 'rollback' ? (Arr::size($stack) > 1 ? $stack[Arr::size($stack) - 2] : null) : ($binding['target'] ?? null);
            if (!Arr::has(['activate', 'rollback'], $operation, true) || ($binding['expected_revision'] ?? null) !== $revision
                || ($receipt['schema_version'] ?? null) !== 1 || ($receipt['scope'] ?? null) !== $this->scope
                || ($receipt['request_id'] ?? null) !== (string) $id || ($receipt['operation'] ?? null) !== $operation
                || ($receipt['expected_revision'] ?? null) !== $revision || ($receipt['from'] ?? null) !== $from
                || !Arr::hasKey($receipt, 'to') || $receipt['to'] !== $target || !Flag::isBool($receipt['applied'] ?? null)
                || ($target !== null && !isset($state['bundles'][$target]))) { throw new InvalidArgumentException('Inconsistent bundle activation history.'); }
            $evaluation = $receipt['evaluation'] ?? null;
            if ($operation === 'activate' && (!Arr::is($evaluation) || !Flag::isBool($evaluation['accepted'] ?? null) || !Arr::is($evaluation['evidence'] ?? null))) { throw new InvalidArgumentException('Missing retained evaluation.'); }
            $reason = $operation === 'rollback' && $target === null ? 'no_previous_bundle'
                : ($operation === 'activate' && !$evaluation['accepted'] ? 'evaluation_rejected'
                : (($receipt['decision']['status'] ?? null) === GovernanceDecision::STATUS_APPROVED ? null : 'governance_not_approved'));
            if (($receipt['reason'] ?? null) !== $reason || $receipt['applied'] !== ($reason === null)) { throw new InvalidArgumentException('Retained decision contradicts activation.'); }
            if ($receipt['applied']) {
                if ($operation === 'activate') { $stack[] = $target; }
                else { $stack = Arr::make($stack)->slice(0, -1)->val(); }
                ++$revision;
            }
            if (($receipt['revision'] ?? null) !== $revision) { throw new InvalidArgumentException('Incorrect retained activation revision.'); }
        }
        if ($state['stack'] !== $stack || $state['revision'] !== $revision) { throw new InvalidArgumentException('Active bundle differs from its retained history.'); }
        return $state;
    }

    /** Resolve the last accepted complete set, rather than individual mutable bindings. */
    private function activeBundle(array $state): LearningBundle { return LearningBundle::fromArray($state['bundles'][$state['stack'][Arr::size($state['stack']) - 1]]); }
    /** Scope compatibility is checked even when component digests happen to match. */
    private function checkScope(LearningBundle $bundle): void { if ($bundle->scope() !== $this->scope) { throw new InvalidArgumentException('Bundle belongs to another scope.'); } }
    /** Reject reads and writes while publication outcome is uncertain or host code is running. */
    private function available(): void { if ($this->busy || $this->uncertain !== null) { throw new LogicException('Bundle owner is busy or requires recovery.'); } }
    /** User callbacks cannot reenter the same transition owner. */
    private function enter(): void { $this->available(); $this->busy = true; }
}
