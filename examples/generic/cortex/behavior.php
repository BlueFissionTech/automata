<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\LLM\Agent\State\AgentState;
use BlueFission\Behavioral\Behaviors\Action;
use BlueFission\Behavioral\Behaviors\State;

// AgentState composes DevElation's StateMachine trait and behavioral registry.
// These fixture behaviors extend it without introducing a provider or host runtime.
$reviewing = new State('IsReviewingRoute');
$ready = new State('IsReadyToDispatchRoute');
$propose = new Action('DoProposeRoute');
$dispatch = new Action('DoDispatchRoute');

$agentState = new AgentState();
$agentState->behavior($reviewing);
$agentState->behavior($ready);
$agentState->behavior($propose);
$agentState->behavior($dispatch);

$agentState->allowInState($reviewing->name(), $propose->name());
$agentState->denyInState($reviewing->name(), $dispatch->name());
$agentState->allowInState($ready->name(), $dispatch->name());

$checks = [];
$agentState->enter($reviewing->name());

$checks['review_state_active'] = $agentState->is($reviewing->name());
$checks['proposal_allowed_during_review'] = $agentState->canPerform($propose->name());
$checks['dispatch_denied_during_review'] = !$agentState->canPerform($dispatch->name());

$agentState->leave($reviewing->name());
$checks['review_state_left'] = !$agentState->is($reviewing->name());

$agentState->enter($ready->name());
$localGate = $agentState->canPerform($dispatch->name());
$checks['dispatch_allowed_when_ready'] = $localGate;

// A local behavior gate is never a grant of host authority.
$hostDenial = new AutonomyDecision();
$checks['host_denial_still_blocks_dispatch'] = $localGate && !$hostDenial->field('allowed');

$hostApproval = new AutonomyDecision([
    'allowed' => true,
    'code' => AutonomyDecision::CODE_ALLOWED,
    'subject_id' => 'fixture-agent',
    'capability_id' => 'route.dispatch',
    'capability_version' => '1',
]);

$requested = $localGate && $hostApproval->field('allowed');
$receipt = null; // No receiver was invoked, so execution is not confirmed.
$checks['approved_request_is_not_confirmation'] = $requested && $receipt === null;

$passed = !Arr::has($checks, false, true);

echo json_encode([
    'experiment' => 'cortex-behavior-v1',
    'passed' => $passed,
    'checks' => $checks,
    'evidence' => [
        'active_states' => $agentState->activeStates(),
        'behavior' => $dispatch->name(),
        'local_gate' => $localGate,
        'host_decision' => $hostApproval->toArray(),
        'request_status' => $requested ? 'requested' : 'denied',
        'execution_receipt' => $receipt,
    ],
    'limits' => [
        'StateMachine gates local behaviors only; the host owns authorization and effects.',
        'An authorized request does not prove execution without a receiver receipt.',
        'This fixture performs no external action and provides no durable recovery.',
    ],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;

exit($passed ? 0 : 1);
