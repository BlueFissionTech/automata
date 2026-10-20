<?php

namespace CortexExample\Scenarios;

use BlueFission\Arr;
use LogicException;
use RuntimeException;

/** Resource replacement, current approval, receipt loss and uncertain effects. */
final class OperationsScenario
{
    /** All mutations affect this local ledger, never an external service. */
    public function run(): array
    {
        $effects = [];
        $loseResult = false;
        $host = new EffectHost('operations', static function ($proposal) use (&$effects, &$loseResult): string {
            if ($proposal !== ['resource' => 'backup', 'revision' => 2]) {
                throw new LogicException('Stale or unknown resource proposal.');
            }
            $effects[] = $proposal;
            if ($loseResult) { throw new RuntimeException('Synthetic result loss after effect.'); }
            return 'applied';
        });
        $old = ScenarioRuntime::workflow('operation-primary', [
            'primary' => static fn () => ['resource' => 'primary', 'revision' => 1],
        ], [], ['primary'], []);
        // The host receives a newer availability revision before dispatch.
        $old->cancel();
        $replacement = ScenarioRuntime::workflow('operation-backup', [
            'availability' => static fn () => ['primary' => false, 'backup' => true, 'revision' => 2],
            'backup' => static fn ($input) => ['resource' => 'backup',
                'revision' => $input['predecessors']['availability']['output']['revision']],
        ], [['availability', 'backup']], ['backup'], []);
        $plan = ScenarioRuntime::finish($replacement);
        $proposal = $plan['outputs']['backup'];
        $denied = $host->response('approval-revoked', $proposal);
        $deniedRelease = $denied->prepare(0);
        $denied->acknowledge($deniedRelease['id'], $host->deliver($deniedRelease, false));
        $denialSafe = $effects === [] && $denied->prepare(1) === null;

        $response = $host->response('approved-operation', $proposal);
        $release = $response->prepare(0);
        $pending = $response->snapshot();
        $receipt = $host->deliver($release, true);
        // Drop the caller acknowledgement, retain the receiver receipt, restore.
        $restored = $host->agent->restoreResponse($pending);
        $replayed = $restored->prepare(1);
        $restored->acknowledge($replayed['id'], $host->deliver($replayed, true));
        $confirmation = $restored->prepare(2);
        $once = Arr::size($effects) === 1 && $receipt['action']['successful'];

        $loseResult = true;
        $uncertain = $host->response('unknown-operation', $proposal);
        $unknownRelease = $uncertain->prepare(0);
        $rejections = 0;
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try { $host->deliver($unknownRelease, true); }
            catch (LogicException) { ++$rejections; }
        }
        $checks = [
            'stale_plan_not_dispatched' => $old->result()->toArray()['dispatches'] === 0,
            'replacement_uses_current_resource_revision' => $proposal === ['resource' => 'backup', 'revision' => 2],
            'revocation_prevents_effect_and_success' => $denialSafe,
            'lost_ack_retains_release_identity' => $replayed === $release,
            'lost_ack_does_not_repeat_effect' => $once,
            'confirmation_follows_observed_effect' => $confirmation['fragments'][0]['id'] === 'confirmation'
                && self::validCompletion($confirmation['fragments'][0]['payload'], $effects),
            'unknown_effect_never_retried' => $rejections === 2 && Arr::size($effects) === 2,
            'unknown_effect_keeps_pending_action' => $uncertain->prepare(1) === $unknownRelease,
            'fabricated_success_control_rejected' => self::validCompletion(['completed' => true], []) === false,
        ];
        return ['scenario' => 'operations-v1', 'status' => Arr::has($checks, false, true) ? 'FAIL' : 'PASS',
            'checks' => $checks, 'effects' => $effects, 'workflow' => $plan,
            'scope' => 'Synthetic host ledger and process-local recovery; no external effects or durable transactions.'];
    }

    /** Independent oracle requires an effect, not just a success-shaped payload. */
    public static function validCompletion(array $claim, array $effects): bool
    {
        return ($claim['completed'] ?? false) === true && isset($effects[0])
            && ($claim['proposal'] ?? null) === $effects[0];
    }
}
