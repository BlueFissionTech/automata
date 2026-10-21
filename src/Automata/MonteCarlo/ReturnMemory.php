<?php

namespace BlueFission\Automata\MonteCarlo;

use BlueFission\{Arr, Num};
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Learning\{Experience, Outcome};
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;
use OverflowException;

/**
 * Retained, attributable return estimates for Monte Carlo action evaluation.
 *
 * Fresh rollouts remain the configured simulation anchor. Explicitly admitted
 * session returns can supply a bounded adjustment; simulated and observed
 * evidence are separate populations and never silently pooled. This class owns
 * neither execution authority nor storage, exploration, credit assignment or
 * model activation. Hosts declare context/projection and exact action semantics.
 */
final class ReturnMemory
{
    private array $entries = [];
    private array $sources = [];
    private array $aggregates = [];

    /** Policy parameters are checkpoint-bound; limits do not encode fixture rules. */
    public function __construct(
        private readonly string $policyId = 'returns',
        private readonly string $policyVersion = '1',
        private readonly float $rewardScale = 1.0,
        private readonly float $priorWeight = 4.0,
        private readonly float $maximumAdjustment = 0.25,
        private readonly int $minimumSamples = 3,
        private readonly int $maximumObservations = 10000,
    ) {
        RecordSnapshot::identifier($policyId, 'policy id');
        RecordSnapshot::identifier($policyVersion, 'policy version');
        RecordSnapshot::number($rewardScale, 'reward scale');
        RecordSnapshot::number($priorWeight, 'prior weight');
        RecordSnapshot::number($maximumAdjustment, 'maximum adjustment', 0.0, $rewardScale);
        if ($rewardScale <= 0 || $priorWeight <= 0 || $minimumSamples < 1 || $maximumObservations < 1) {
            throw new InvalidArgumentException('Scale, prior weight and sample limits must be positive.');
        }
    }

    /**
     * Admit an attached outcome's explicit return; identical replay returns false.
     *
     * Attribution requires context_key, action (finite data, preferably a versioned
     * identity) and origin: observed or simulated. Zero is a measured return and
     * contributes to the empirical denominator; null is unrated and does not.
     * Execution success never supplies a reward. Delayed assessments use new
     * outcome ids under the unchanged experience; old identities cannot be edited.
     */
    public function learn(Experience $experience, string $outcomeId, ?FeedbackSignal $signal): bool
    {
        RecordSnapshot::identifier($outcomeId, 'outcome id');
        $matches = Arr::make($experience->outcomes())->filter(static fn (Outcome $outcome) => $outcome->id() === $outcomeId)->values()->val();
        if ($matches === []) { throw new InvalidArgumentException('Return must reference an attached outcome.'); }
        $outcome = $matches[0]->toArray();
        $attribution = $outcome['attribution'];
        $context = RecordSnapshot::identifier($attribution['context_key'] ?? null, 'context key');
        $origin = self::origin($attribution['origin'] ?? null);
        if (!Arr::hasKey($attribution, 'action')) { throw new InvalidArgumentException('Return action identity is required.'); }
        $action = RecordSnapshot::copy($attribution['action']);
        $reward = $signal === null ? null : RecordSnapshot::number($signal->value(), 'return', -$this->rewardScale, $this->rewardScale);
        $source = $experience->toArray();
        unset($source['outcomes']);
        $sourceKey = RecordSnapshot::fingerprint($experience->id());
        $sourceFingerprint = RecordSnapshot::fingerprint($source);
        if (isset($this->sources[$sourceKey]) && $this->sources[$sourceKey] !== $sourceFingerprint) {
            throw new InvalidArgumentException('Experience identity already has different source evidence.');
        }
        $key = RecordSnapshot::fingerprint([$experience->id(), $outcomeId]);
        $fingerprint = RecordSnapshot::fingerprint([$source, $outcome, $reward]);
        if (isset($this->entries[$key])) {
            if ($this->entries[$key]['fingerprint'] !== $fingerprint) { throw new InvalidArgumentException('Outcome already has a different return.'); }
            return false;
        }
        if (Arr::size($this->entries) >= $this->maximumObservations) {
            // Silent eviction would allow a replayed old outcome to learn twice.
            throw new OverflowException('Return capacity reached; checkpoint and rotate explicitly.');
        }
        $evaluation = $reward === null ? 'absent' : ($reward == 0.0 ? 'neutral' : ($reward > 0 ? 'positive' : 'negative'));
        $statsKey = self::key($context, $action, $origin);
        $stats = $this->aggregates[$statsKey] ?? self::emptyStatistics();
        $counter = $evaluation === 'absent' ? 'unrated_samples' : $evaluation . '_samples';
        $stats[$counter] = (int) Num::make($stats[$counter])->add(1)->val();
        if ($reward !== null) {
            $stats['rated_samples'] = (int) Num::make($stats['rated_samples'])->add(1)->val();
            // Normalize before summing so large finite reward units do not overflow.
            $normalized = Num::make($reward)->divide($this->rewardScale)->val();
            $stats['normalized_sum'] = (float) Num::make($stats['normalized_sum'])->add($normalized)->val();
        }
        $this->aggregates[$statsKey] = $stats;
        $this->sources[$sourceKey] = $sourceFingerprint;
        $this->entries[$key] = ['fingerprint' => $fingerprint,
            'evidence' => ['experience' => [...$source, 'outcomes' => [$outcome]], 'outcome_id' => $outcomeId, 'reward' => $reward],
            'receipt' => ['policy_id' => $this->policyId, 'policy_version' => $this->policyVersion,
                'experience_id' => $experience->id(), 'outcome_id' => $outcomeId, 'source' => $outcome['source'],
                'context_key' => $context, 'action' => $action, 'origin' => $origin,
                'evaluation' => $evaluation, 'reward' => $reward, 'execution_successful' => $outcome['successful']]];
        return true;
    }

    /** Inspect one exact population; an unmeasured mean remains null, not zero. */
    public function statistics(string $contextKey, mixed $action, string $origin = 'observed'): array
    {
        $stats = $this->aggregates[self::key($contextKey, $action, $origin)] ?? self::emptyStatistics();
        return [...$stats, 'mean_reward' => $stats['rated_samples'] === 0 ? null
            : (float) Num::make($stats['normalized_sum'])->divide($stats['rated_samples'])->multiply($this->rewardScale)->val()];
    }

    /**
     * Rank only the supplied rollout candidates; do not mutate their statistics.
     *
     * The retained posterior is shrunk toward zero by priorWeight and capped by
     * maximumAdjustment in reward units. Sparse evidence leaves the simulation
     * anchor unchanged. All reward units must match rewardScale. An unvisited
     * action has no measured anchor: its score stays null and sorts last. The
     * report exposes raw rollouts, origin and empirical counts for host evaluation;
     * score is neither a probability nor permission to execute the action.
     */
    public function rank(SearchResult $result, string $contextKey, string $origin = 'observed'): array
    {
        RecordSnapshot::identifier($contextKey, 'context key');
        self::origin($origin);
        $rows = []; $seen = [];
        foreach ($result->getStatistics() as $position => $statistics) {
            if (!$statistics instanceof ActionStatistics) { throw new InvalidArgumentException('Expected rollout statistics.'); }
            $action = RecordSnapshot::copy($statistics->getAction());
            $key = RecordSnapshot::fingerprint($action);
            if (isset($seen[$key])) { throw new InvalidArgumentException('Rollout candidates must have distinct action identities.'); }
            $seen[$key] = true;
            $retained = $this->statistics($contextKey, $action, $origin);
            $anchor = $statistics->getVisits() === 0 ? null
                : RecordSnapshot::number($statistics->getMeanReward(), 'rollout mean', -$this->rewardScale, $this->rewardScale);
            $adjustment = 0.0; $score = null;
            if ($anchor !== null) {
                $shift = 0.0;
                if ($retained['rated_samples'] >= $this->minimumSamples) {
                    $denominator = Num::make($this->priorWeight)->add($retained['rated_samples'])->val();
                    $fraction = Num::make($this->maximumAdjustment)->divide($this->rewardScale)->val();
                    $shift = Num::make($retained['normalized_sum'])->divide($denominator)->multiply($fraction)->val();
                }
                $score = $anchor;
                if ($shift != 0.0) {
                    // Work in normalized units to avoid overflow. With no shift,
                    // retain the exact anchor rather than round-tripping its units.
                    // Num::min/max return scalars in the supported dependency.
                    $upper = Num::make($anchor)->divide($this->rewardScale)->add($shift)->min(1.0);
                    $bounded = Num::make($upper)->max(-1.0);
                    $score = (float) Num::make($bounded)->multiply($this->rewardScale)->val();
                }
                $adjustment = (float) Num::make($score)->subtract($anchor)->val();
            }
            $rows[] = ['action' => $action, 'anchor' => $anchor, 'adjustment' => $adjustment, 'score' => $score,
                'origin' => $origin, 'retained' => $retained, 'fresh_rollouts' => $statistics->toArray(), 'position' => $position];
        }
        return Arr::make($rows)->sort(static function (array $left, array $right): int {
            if ($left['score'] === null || $right['score'] === null) {
                $missingOrder = ($left['score'] === null ? 1 : 0) <=> ($right['score'] === null ? 1 : 0);
                if ($missingOrder !== 0) { return $missingOrder; }
            }
            return ($right['score'] <=> $left['score']) ?: ($left['position'] <=> $right['position']);
        })->map(static function (array $row): array { unset($row['position']); return $row; })->val();
    }

    /** Detached receipts make execution status and feedback semantics inspectable. */
    public function receipts(): array { return Arr::make($this->entries)->map(static fn ($entry) => $entry['receipt'])->values()->val(); }

    /** Data-only evidence; the fingerprint detects corruption, not malicious rewriting. */
    public function checkpoint(): array
    {
        $payload = ['schema_version' => 1, 'policy' => $this->configuration(),
            'evidence' => Arr::make($this->entries)->map(static fn ($entry) => $entry['evidence'])->values()->val()];
        return [...$payload, 'fingerprint' => RecordSnapshot::fingerprint($payload)];
    }

    /** Recompute an isolated memory under identical policy; do not trust stored scores. */
    public function restore(array $checkpoint): self
    {
        $payload = RecordSnapshot::copy($checkpoint);
        $fingerprint = $payload['fingerprint'] ?? null;
        unset($payload['fingerprint']);
        if (($payload['schema_version'] ?? null) !== 1 || $fingerprint !== RecordSnapshot::fingerprint($payload)
            || Arr::make($payload)->keys()->diff(['schema_version', 'policy', 'evidence'])->val() !== []
            || RecordSnapshot::fingerprint($payload['policy'] ?? null) !== RecordSnapshot::fingerprint($this->configuration())
            || !Arr::is($payload['evidence'] ?? null) || !Arr::check($payload['evidence'], 'array_is_list')
            || Arr::size($payload['evidence']) > $this->maximumObservations) {
            throw new InvalidArgumentException('Return checkpoint integrity, schema or policy mismatch.');
        }
        $candidate = clone $this;
        $candidate->entries = []; $candidate->sources = []; $candidate->aggregates = [];
        foreach ($payload['evidence'] as $entry) {
            if (!Arr::is($entry) || !Arr::is($entry['experience'] ?? null) || !Arr::hasKey($entry, 'reward')
                || Arr::make($entry)->keys()->diff(['experience', 'outcome_id', 'reward'])->val() !== []) {
                throw new InvalidArgumentException('Malformed retained return evidence.');
            }
            $outcomeId = RecordSnapshot::identifier($entry['outcome_id'] ?? null, 'outcome id');
            $signal = $entry['reward'] === null ? null
                : new FeedbackSignal(RecordSnapshot::number($entry['reward'], 'return', -$this->rewardScale, $this->rewardScale));
            if (!$candidate->learn(new Experience($entry['experience']), $outcomeId, $signal)) {
                throw new InvalidArgumentException('Duplicate return identity in checkpoint.');
            }
        }
        return $candidate;
    }

    /** Keep all estimator/retention parameters bound to the checkpoint. */
    private function configuration(): array
    {
        return ['policy_id' => $this->policyId, 'policy_version' => $this->policyVersion,
            'reward_scale' => $this->rewardScale, 'prior_weight' => $this->priorWeight,
            'maximum_adjustment' => $this->maximumAdjustment, 'minimum_samples' => $this->minimumSamples,
            'maximum_observations' => $this->maximumObservations];
    }

    /** Type-preserving context/action keys prevent accidental transfer across populations. */
    private static function key(string $context, mixed $action, string $origin): string
    {
        return RecordSnapshot::fingerprint([RecordSnapshot::identifier($context, 'context key'), self::origin($origin), $action]);
    }

    /** The provenance declaration is explicit; authenticating the source remains host-owned. */
    private static function origin(mixed $origin): string
    {
        if (!Arr::has(['observed', 'simulated'], $origin, true)) { throw new InvalidArgumentException('Return origin must be observed or simulated.'); }
        return $origin;
    }

    /** Measured zero returns count; missing evaluations do not enter the mean. */
    private static function emptyStatistics(): array
    {
        return ['rated_samples' => 0, 'positive_samples' => 0, 'negative_samples' => 0,
            'neutral_samples' => 0, 'unrated_samples' => 0, 'normalized_sum' => 0.0];
    }
}
