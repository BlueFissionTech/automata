<?php

namespace BlueFission\Automata\Response;

use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;
use LogicException;
use Throwable;

/** Single-writer state machine. Hosts own storage, authorization and receiver idempotency. */
final class ResponseComposer
{
    private const MAX_EVENTS = 10000;
    private array $definitions = [];
    private array $states = [];
    private array $events = [];
    private array $releases = [];
    private ?string $pending = null;
    private ?string $cancellation = null;
    private int $clock = -1;

    public function __construct(private readonly ResponseEnvelope $envelope, private readonly ResponsePolicy $policy)
    {
        foreach ($envelope->fragments() as $fragment) {
            $this->definitions[$fragment->id()] = $fragment->toArray();
            $this->states[$fragment->id()] = ['status' => 'pending', 'completion' => 0.0,
                'confidence' => null, 'payload' => null, 'resolution' => null, 'reason' => null, 'receipt' => null];
        }
    }

    public function progress(string $id, mixed $completion): self
    {
        $this->writable($id);
        $completion = RecordSnapshot::number($completion, 'completion', 0.0, 1.0);
        if ($completion < $this->states[$id]['completion']) { throw new InvalidArgumentException('Progress cannot regress.'); }
        $this->states[$id]['completion'] = $completion;
        $this->events[] = ['type' => 'progress', 'id' => $id, 'completion' => $completion];
        return $this;
    }

    public function resolve(string $id, mixed $payload, mixed $confidence = null): self
    {
        $this->writable($id);
        $payload = RecordSnapshot::canonical(RecordSnapshot::copy($payload, 8));
        $confidence = $confidence === null ? null : RecordSnapshot::number($confidence, 'confidence', 0.0, 1.0);
        $this->states[$id] = [...$this->states[$id], 'status' => 'ready', 'completion' => 1.0,
            'confidence' => $confidence, 'payload' => $payload, 'resolution' => 'primary'];
        $this->events[] = ['type' => 'resolve', 'id' => $id, 'payload' => $payload, 'confidence' => $confidence];
        return $this;
    }

    public function fail(string $id, string $reason): self
    {
        $this->writable($id);
        RecordSnapshot::identifier($reason, 'failure reason');
        $this->failure($id, $reason, 'failed');
        $this->events[] = ['type' => 'fail', 'id' => $id, 'reason' => $reason];
        return $this;
    }

    public function cancel(string $reason): self
    {
        RecordSnapshot::identifier($reason, 'cancellation reason');
        if ($this->cancellation !== null) { return $this; }
        $this->capacity(terminal: true);
        $inflight = $this->pending === null ? [] : Arr::make($this->releases[$this->pending]['release']['fragments'])
            ->map(static fn (array $fragment): string => $fragment['id'])->values()->val();
        foreach ($this->states as $id => $state) {
            if (Arr::has(['pending', 'ready'], $state['status'], true) && !Arr::has($inflight, (string) $id, true)) {
                $this->states[$id]['status'] = 'cancelled';
                $this->states[$id]['reason'] = $reason;
                $this->states[$id]['completion'] = 0.0;
            }
        }
        $this->cancellation = $reason;
        $this->events[] = ['type' => 'cancel', 'reason' => $reason];
        return $this;
    }

    /** Prepare data only. Re-observing a pending batch retains its id and exact payload. */
    public function prepare(int $nowMs): ?array
    {
        if ($nowMs < 0 || $nowMs < $this->clock) { throw new InvalidArgumentException('Clock must be nonnegative and monotonic.'); }
        if ($this->cancellation !== null) { return null; }
        $this->capacity();
        $this->clock = $nowMs;
        $this->events[] = ['type' => 'prepare', 'now_ms' => $nowMs];
        foreach ($this->definitions as $id => $definition) {
            if ($this->states[$id]['status'] === 'pending' && $definition['deadline_ms'] !== null
                && $nowMs >= $definition['deadline_ms']) { $this->failure((string) $id, 'deadline_exceeded', 'timed_out'); }
        }
        if ($this->pending !== null) { return $this->releases[$this->pending]['release']; }
        if ($this->state()['completion'] < $this->policy->threshold()) { return null; }
        foreach ($this->definitions as $id => $definition) {
            $state = $this->states[$id];
            if ($definition['blocking'] && ($state['resolution'] !== 'primary'
                || !Arr::has(['ready', 'delivered'], $state['status'], true)
                || ($state['receipt'] !== null && !$state['receipt']['successful']))) { return null; }
        }
        $fragments = Arr::make($this->envelope->fragments())
            ->filter(fn (ResponseFragment $fragment): bool => $this->releasable($fragment->id()))
            ->sort(static fn (ResponseFragment $a, ResponseFragment $b): int => $b->toArray()['priority'] <=> $a->toArray()['priority'])
            ->map(fn (ResponseFragment $fragment): array => [...$fragment->toArray(),
                'payload' => $this->states[$fragment->id()]['payload'],
                'confidence' => $this->states[$fragment->id()]['confidence'],
                'resolution' => $this->states[$fragment->id()]['resolution']])->values()->val();
        if (Arr::isEmpty($fragments)) { return null; }
        $sequence = Arr::count($this->releases) + 1;
        $id = 'response-release-' . RecordSnapshot::fingerprint([$this->envelope->id(), $sequence, $fragments]);
        $release = ['id' => $id, 'response_id' => $this->envelope->id(), 'sequence' => $sequence,
            'prepared_at_ms' => $nowMs, 'fragments' => $fragments, 'trace' => $this->envelope->trace()];
        $this->releases[$id] = ['release' => $release, 'receipts' => null];
        $this->pending = $id;
        return $release;
    }

    /** Receipts attest terminal receiver results, not merely transport acceptance. */
    public function acknowledge(string $id, array $receipts): bool
    {
        if (!isset($this->releases[$id])) { throw new InvalidArgumentException('Unknown response release.'); }
        $receipts = RecordSnapshot::copy($receipts, 8);
        $fragments = $this->releases[$id]['release']['fragments'];
        if (Arr::count($receipts) !== Arr::count($fragments)) { throw new InvalidArgumentException('Every fragment requires a receiver receipt.'); }
        $normalized = [];
        foreach ($fragments as $fragment) {
            $receipt = $receipts[$fragment['id']] ?? null;
            if (!Arr::is($receipt) || !Flag::isBool($receipt['successful'] ?? null) || !Arr::is($receipt['evidence'] ?? null)
                || Arr::count($receipt) !== 2) { throw new InvalidArgumentException('Receipts require a boolean result and evidence map.'); }
            $normalized[$fragment['id']] = ['successful' => $receipt['successful'], 'evidence' => $receipt['evidence']];
        }
        $normalized = RecordSnapshot::canonical($normalized);
        if ($this->releases[$id]['receipts'] !== null) {
            if ($this->releases[$id]['receipts'] !== $normalized) { throw new LogicException('Conflicting receiver receipts.'); }
            return false;
        }
        $this->capacity(terminal: true);
        foreach ($normalized as $fragmentId => $receipt) {
            $this->states[$fragmentId]['status'] = 'delivered';
            $this->states[$fragmentId]['receipt'] = $receipt;
            if (!$receipt['successful']) { $this->states[$fragmentId]['completion'] = 0.0; }
        }
        $this->releases[$id]['receipts'] = $normalized;
        $this->pending = null;
        $this->events[] = ['type' => 'acknowledge', 'id' => $id, 'receipts' => $normalized];
        return true;
    }

    public function state(): array
    {
        $total = Num::make(0.0);
        $completed = Num::make(0.0);
        $confidence = Num::make(0.0);
        $known = true;
        foreach ($this->definitions as $id => $definition) {
            $state = $this->states[$id];
            $total->add($definition['weight']);
            $contribution = Num::make($definition['weight'])->multiply($state['completion'])->val();
            $completed->add($contribution);
            if ($contribution > 0) {
                if ($state['confidence'] === null) { $known = false; }
                else { $confidence->add(Num::make($contribution)->multiply($state['confidence'])->val()); }
            }
        }
        $completion = Num::make($completed->val())->divide($total->val())->val();
        return ['response_id' => $this->envelope->id(), 'completion' => $completion,
            'confidence' => $known && $completed->val() > 0 ? $confidence->divide($completed->val())->val() : null,
            'cancelled' => $this->cancellation !== null, 'cancellation_reason' => $this->cancellation,
            'fragments' => $this->states, 'pending_release_id' => $this->pending, 'releases' => $this->releases];
    }

    public function snapshot(): array
    {
        return ['schema_version' => 1, 'envelope' => $this->envelope->toArray(),
            'policy' => $this->policy->toArray(), 'events' => $this->events];
    }

    /** Replays validated state transitions; checkpoints are host-trusted evidence, not authorization. */
    public static function restore(array $record): self
    {
        try {
            $record = RecordSnapshot::copy($record);
            if (($record['schema_version'] ?? null) !== 1 || !Arr::is($record['events'] ?? null)
                || !Arr::check($record['events'], 'array_is_list') || Arr::count($record['events']) > self::MAX_EVENTS + 2) {
                throw new InvalidArgumentException('Unsupported or malformed response checkpoint.');
            }
            $envelope = $record['envelope'] ?? [];
            if (!Arr::is($envelope) || !Arr::is($envelope['fragments'] ?? null) || !Arr::is($envelope['trace'] ?? null)
                || !Arr::is($record['policy'] ?? null)) { throw new InvalidArgumentException('Malformed response contracts.'); }
            $composer = new self(new ResponseEnvelope(RecordSnapshot::identifier($envelope['id'] ?? null, 'response id'),
                Arr::make($envelope['fragments'])->map(static fn (array $fragment): ResponseFragment => new ResponseFragment($fragment))
                    ->values()->val(), $envelope['trace']), new ResponsePolicy($record['policy']));
            $fields = ['progress' => ['type', 'id', 'completion'], 'resolve' => ['type', 'id', 'payload', 'confidence'],
                'fail' => ['type', 'id', 'reason'], 'cancel' => ['type', 'reason'],
                'prepare' => ['type', 'now_ms'], 'acknowledge' => ['type', 'id', 'receipts']];
            foreach ($record['events'] as $event) {
                if (!Arr::is($event) || !Str::is($event['type'] ?? null) || !isset($fields[$event['type']])
                    || Arr::count($event) !== Arr::count($fields[$event['type']])
                    || array_diff(array_keys($event), $fields[$event['type']]) !== []) {
                    throw new InvalidArgumentException('Malformed response event.');
                }
                switch ($event['type']) {
                    case 'progress': $composer->progress($event['id'], $event['completion']); break;
                    case 'resolve': $composer->resolve($event['id'], $event['payload'], $event['confidence']); break;
                    case 'fail': $composer->fail($event['id'], $event['reason']); break;
                    case 'cancel': $composer->cancel($event['reason']); break;
                    case 'prepare':
                        if (!Num::isInt($event['now_ms'])) { throw new InvalidArgumentException('Invalid event clock.'); }
                        $composer->prepare($event['now_ms']); break;
                    case 'acknowledge': $composer->acknowledge($event['id'], $event['receipts']); break;
                }
            }
            if (RecordSnapshot::canonical($composer->snapshot()) !== RecordSnapshot::canonical($record)) {
                throw new InvalidArgumentException('Inconsistent response checkpoint.');
            }
            return $composer;
        } catch (Throwable $error) {
            throw new InvalidArgumentException('Invalid response checkpoint: ' . $error->getMessage(), 0, $error);
        }
    }

    private function releasable(string $id): bool
    {
        if ($this->states[$id]['status'] !== 'ready') { return false; }
        foreach ($this->definitions[$id]['dependencies'] as $dependency) {
            $state = $this->states[$dependency];
            if ($state['status'] !== 'delivered' || $state['resolution'] !== 'primary' || !$state['receipt']['successful']) { return false; }
        }
        return true;
    }

    private function writable(string $id): void
    {
        if (!isset($this->states[$id])) { throw new InvalidArgumentException('Unknown response fragment.'); }
        if ($this->cancellation !== null || $this->states[$id]['status'] !== 'pending') { throw new LogicException('Fragment is no longer writable.'); }
        $this->capacity();
    }

    private function capacity(bool $terminal = false): void
    {
        // Reserve a slot each for final acknowledgement and cancellation.
        if (Arr::count($this->events) >= self::MAX_EVENTS + ($terminal ? 2 : 0)) {
            throw new LogicException('Response event limit reached; reconcile and cancel before starting a new response.');
        }
    }

    private function failure(string $id, string $reason, string $status): void
    {
        $fallback = $this->definitions[$id]['fallback'];
        $this->states[$id] = [...$this->states[$id], 'status' => $fallback === null ? $status : 'ready',
            'completion' => $fallback === null ? 0.0 : 1.0, 'payload' => $fallback['payload'] ?? null,
            'confidence' => $fallback['confidence'] ?? null, 'resolution' => $fallback === null ? null : 'fallback', 'reason' => $reason];
    }
}
