<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdvisor, StrategyRouteAdvice, StrategyRouteRequest, StrategyRouteResult};
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;
use OverflowException;

/**
 * Explicit session evaluations adjust an anchored, context-local preference.
 *
 * This bounded estimator supplies advice to the existing governed router. It
 * neither invents rewards from execution status nor grants execution authority.
 * Hosts own admission, attribution and the versioned context/signature mapping.
 */
final class AnchoredRouteAdvisor implements IStrategyRouteAdvisor
{
    private array $anchors = [];
    private array $statistics = [];
    private array $entries = [];
    private array $sources = [];

    /**
     * @param array<int, array{id:string, version:string, weight:float|int}> $anchors
     * Limits are host policy, not fixture-specific topology or modality limits.
     * A positive prior retains resistance to small or contradictory samples.
     */
    public function __construct(
        array $anchors,
        private readonly string $policyId = 'anchored',
        private readonly string $policyVersion = '1',
        private readonly float $rewardScale = 1.0,
        private readonly float $priorWeight = 4.0,
        private readonly float $maximumAdjustment = 0.25,
        private readonly int $minimumSamples = 3,
        private readonly int $maximumFeedback = 10000,
        private readonly float $defaultAnchor = 0.5,
    ) {
        RecordSnapshot::identifier($policyId, 'policy id');
        RecordSnapshot::identifier($policyVersion, 'policy version');
        RecordSnapshot::number($rewardScale, 'reward scale');
        RecordSnapshot::number($priorWeight, 'prior weight');
        RecordSnapshot::number($maximumAdjustment, 'maximum adjustment', 0, 1);
        RecordSnapshot::number($defaultAnchor, 'default anchor', 0, 1);
        if ($rewardScale <= 0 || $priorWeight <= 0 || $minimumSamples < 1 || $maximumFeedback < 1) {
            throw new InvalidArgumentException('Scale, prior weight and sample limits must be positive.');
        }
        foreach ($anchors as $anchor) {
            if (!Arr::is($anchor)) {
                throw new InvalidArgumentException('Anchors must be strategy identity and weight records.');
            }
            $key = self::strategyKey($anchor['id'] ?? null, $anchor['version'] ?? null);
            if (Arr::hasKey($this->anchors, $key)) {
                throw new InvalidArgumentException('An exact strategy version can have only one anchor.');
            }
            $this->anchors[$key] = RecordSnapshot::number($anchor['weight'] ?? null, 'anchor weight', 0, 1);
        }
    }

    /**
     * Admit one attached evaluation; identical replay returns false without writes.
     *
     * Zero and null are retained separately and never change the directional
     * denominator. A later assessment must have its own outcome identity; it
     * cannot rewrite an earlier evaluation or silently turn an unrated event
     * into a reward. All validation precedes mutation of statistics or receipts.
     */
    public function learn(Experience $experience, string $outcomeId, ?FeedbackSignal $signal): bool
    {
        RecordSnapshot::identifier($outcomeId, 'outcome id');
        $matches = Arr::make($experience->outcomes())
            ->filter(static fn (Outcome $outcome): bool => $outcome->id() === $outcomeId)
            ->values()->val();
        if (Arr::isEmpty($matches)) {
            throw new InvalidArgumentException('Feedback must reference an attached outcome.');
        }
        $outcome = $matches[0]->toArray();
        $attribution = $outcome['attribution'];
        $strategy = self::strategyKey($attribution['strategy_id'] ?? null, $attribution['strategy_version'] ?? null);
        $context = RecordSnapshot::identifier($attribution['context_key'] ?? null, 'context key');
        $reward = $signal === null ? null
            : RecordSnapshot::number($signal->value(), 'reward', -$this->rewardScale, $this->rewardScale);
        $source = $experience->toArray();
        // Appended outcomes do not change the identity of the source session.
        unset($source['outcomes']);
        $sourceKey = RecordSnapshot::fingerprint($experience->id());
        $sourceFingerprint = RecordSnapshot::fingerprint($source);
        if (isset($this->sources[$sourceKey]) && $this->sources[$sourceKey] !== $sourceFingerprint) {
            throw new InvalidArgumentException('Experience identity already has different source evidence.');
        }
        $key = RecordSnapshot::fingerprint([$experience->id(), $outcomeId]);
        $fingerprint = RecordSnapshot::fingerprint([$source, $outcome, $reward]);
        if (isset($this->entries[$key])) {
            if ($this->entries[$key]['fingerprint'] !== $fingerprint) {
                throw new InvalidArgumentException('Outcome identity already has a different evaluation.');
            }
            return false;
        }
        if (Arr::size($this->entries) >= $this->maximumFeedback) {
            // Never evict identities silently: that would permit duplicate learning.
            throw new OverflowException('Feedback capacity reached; checkpoint and rotate through an explicit policy.');
        }
        $evaluation = $reward === null ? 'absent' : ($reward == 0.0 ? 'neutral' : ($reward > 0 ? 'positive' : 'negative'));
        $statsKey = RecordSnapshot::fingerprint([$context, $strategy]);
        $stats = $this->statistics[$statsKey] ?? self::emptyStatistics();
        $counter = $evaluation === 'absent' ? 'unrated_samples'
            : ($evaluation === 'neutral' ? 'neutral_samples' : 'directional_samples');
        $stats[$counter] = (int) Num::make($stats[$counter])->add(1)->val();
        if ($counter === 'directional_samples') {
            $normalized = Num::make($reward)->divide($this->rewardScale)->val();
            $stats['reward_sum'] = (float) Num::make($stats['reward_sum'])->add($normalized)->val();
        }
        $this->statistics[$statsKey] = $stats;
        $this->sources[$sourceKey] = $sourceFingerprint;
        $this->entries[$key] = ['fingerprint' => $fingerprint,
            'evidence' => ['experience' => [...$source, 'outcomes' => [$outcome]],
                'outcome_id' => $outcomeId, 'reward' => $reward], 'receipt' => [
            'schema_version' => 1, 'policy_id' => $this->policyId, 'policy_version' => $this->policyVersion,
            'experience_id' => $experience->id(), 'outcome_id' => $outcomeId,
            'source' => $outcome['source'], 'source_fingerprint' => $sourceFingerprint,
            'strategy_id' => $attribution['strategy_id'], 'strategy_version' => $attribution['strategy_version'],
            'context_key' => $context, 'evaluation' => $evaluation, 'reward' => $reward,
            'execution_successful' => $outcome['successful'], 'status' => 'applied',
        ]];
        return true;
    }

    /** Rank only declared exact versions; unseen contexts and versions keep priors. */
    public function advise(StrategyRouteRequest $request, array $definitions): StrategyRouteAdvice
    {
        $rankings = [];
        foreach ($definitions as $definition) {
            if (!Arr::is($definition)) {
                throw new InvalidArgumentException('Strategy definitions must be records.');
            }
            $key = self::strategyKey($definition['id'] ?? null, $definition['version'] ?? null);
            $anchor = $this->anchors[$key] ?? $this->defaultAnchor;
            $stats = $this->statistics[RecordSnapshot::fingerprint([$request->context_key, $key])] ?? self::emptyStatistics();
            $adjustment = 0.0;
            if ($stats['directional_samples'] >= $this->minimumSamples) {
                $denominator = Num::make($this->priorWeight)->add($stats['directional_samples'])->val();
                $adjustment = Num::make($stats['reward_sum'])->divide($denominator)->multiply($this->maximumAdjustment)->val();
            }
            $ceiling = Num::make($anchor)->add($adjustment)->min(1.0);
            $score = (float) Num::make($ceiling)->max(0.0);
            $rankings[$key] = ['score' => $score, 'anchor' => $anchor,
                'confidence' => null, 'metrics' => $stats];
        }
        return new StrategyRouteAdvice(['policy' => StrategyRouteRequest::SELECTION_ADAPTIVE,
            'rankings' => $rankings, 'metadata' => ['policy_id' => $this->policyId,
                'policy_version' => $this->policyVersion, 'context_key' => $request->context_key]]);
    }

    /** A transport result alone provides no human or system evaluation. */
    public function observe(StrategyRouteRequest $request, StrategyRouteResult $result): void {}

    /** Detached audit summaries; these are not a durable checkpoint or training set. */
    public function receipts(): array
    {
        return Arr::make($this->entries)->map(static fn (array $entry): array => $entry['receipt'])->values()->val();
    }

    /**
     * Export admitted evidence, immutable policy and an integrity fingerprint.
     *
     * Persist this data with the host's access, atomic-write and retention policy.
     * The checksum detects accidental alteration, not malicious replacement by
     * someone who can rewrite the checksum. No executable objects or serialized
     * PHP instances are exported. Use a format preserving scalar/float types.
     */
    public function checkpoint(): array
    {
        $payload = ['schema_version' => 1, 'policy' => $this->configuration(),
            'evidence' => Arr::make($this->entries)->map(static fn (array $entry): array => $entry['evidence'])->values()->val()];
        return [...$payload, 'fingerprint' => RecordSnapshot::fingerprint($payload)];
    }

    /**
     * Rebuild a detached candidate under this exact policy; never mutate this one.
     *
     * Replay validation reconstructs statistics and idempotency from evidence,
     * rather than trusting imported scores. The caller decides whether to activate
     * the returned candidate. A policy/anchor change needs explicit migration and
     * evaluation, not silent reuse of a checkpoint with different semantics.
     */
    public function restore(array $checkpoint): self
    {
        $payload = RecordSnapshot::copy($checkpoint);
        $fingerprint = $payload['fingerprint'] ?? null;
        unset($payload['fingerprint']);
        if (($payload['schema_version'] ?? null) !== 1
            || $fingerprint !== RecordSnapshot::fingerprint($payload)
            || RecordSnapshot::fingerprint($payload['policy'] ?? null) !== RecordSnapshot::fingerprint($this->configuration())
            || !Arr::is($payload['evidence'] ?? null)
            || !Arr::check($payload['evidence'], 'array_is_list')
            || Arr::size($payload['evidence']) > $this->maximumFeedback) {
            throw new InvalidArgumentException('Checkpoint integrity, schema, scope or policy does not match.');
        }
        $candidate = clone $this;
        $candidate->entries = [];
        $candidate->sources = [];
        $candidate->statistics = [];
        foreach ($payload['evidence'] as $entry) {
            if (!Arr::is($entry) || !Arr::is($entry['experience'] ?? null) || !Arr::hasKey($entry, 'reward')) {
                throw new InvalidArgumentException('Checkpoint evidence is malformed.');
            }
            $outcomeId = RecordSnapshot::identifier($entry['outcome_id'] ?? null, 'outcome id');
            $reward = $entry['reward'] === null ? null
                : new FeedbackSignal(RecordSnapshot::number($entry['reward'], 'reward', -$this->rewardScale, $this->rewardScale));
            if (!$candidate->learn(new Experience($entry['experience']), $outcomeId, $reward)) {
                throw new InvalidArgumentException('Checkpoint evidence contains duplicate outcome identities.');
            }
        }
        return $candidate;
    }

    /** Every scoring/admission parameter is bound to the saved policy identity. */
    private function configuration(): array
    {
        return ['policy_id' => $this->policyId, 'policy_version' => $this->policyVersion,
            'anchors' => $this->anchors, 'reward_scale' => $this->rewardScale, 'prior_weight' => $this->priorWeight,
            'maximum_adjustment' => $this->maximumAdjustment, 'minimum_samples' => $this->minimumSamples,
            'maximum_feedback' => $this->maximumFeedback, 'default_anchor' => $this->defaultAnchor];
    }

    /** Preserve exact identity and reject aliases in the router's id@version keys. */
    private static function strategyKey(mixed $id, mixed $version): string
    {
        $id = RecordSnapshot::identifier($id, 'strategy id');
        $version = RecordSnapshot::identifier($version, 'strategy version');
        if (Str::make($id)->contains('@') || Str::make($version)->contains('@')) {
            throw new InvalidArgumentException('Strategy identity components cannot contain @.');
        }
        return StrategyRouteAdvice::key($id, $version);
    }

    /** Missing evidence is represented explicitly, including an empty reward sum. */
    private static function emptyStatistics(): array
    {
        return ['directional_samples' => 0, 'neutral_samples' => 0, 'unrated_samples' => 0, 'reward_sum' => 0.0];
    }
}
