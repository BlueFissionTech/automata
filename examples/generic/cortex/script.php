<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\Intelligence;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\Strategy\ScriptStrategy;
use BlueFission\Automata\Strategy\Script\ScriptExecution;
use BlueFission\Automata\Strategy\Routing\Adapter\ScriptRouteAdapter;
use BlueFission\Automata\Strategy\Routing\{StrategyRouteRequest, StrategyRouter};
use BlueFission\Parsing\{Parser, Element};
use BlueFission\Parsing\Contracts\IGenerator;
use BlueFission\Parsing\Registry\{TagRegistry, RendererRegistry, PreparerRegistry, ExecutorRegistry};

// Grammar ownership stays with the host. No registry is swapped during a run.
TagRegistry::registerDefaults();
RendererRegistry::registerDefaults();
PreparerRegistry::registerDefaults();
ExecutorRegistry::registerDefaults();
$source = 'Hello {$name}. {$answer}';
$requests = [];
$approve = static function (array $request) use (&$requests): AutonomyDecision {
    $requests[] = $request;
    return new AutonomyDecision(['allowed' => true, 'subject_id' => $request['subject_id'],
        'capability_id' => $request['capability_id'], 'capability_version' => $request['capability_version']]);
};
// Deterministic generation fixture exercises IGenerator, not provider capability or spend.
$generator = new class implements IGenerator {
    public int $calls = 0;
    public function generate(Element $element): string { $this->calls++; return 'Your request is ready for review.'; }
};
$captured = null;
$prepare = static function (string $source, mixed $input, ScriptExecution $run) use (&$captured): Parser {
    $captured = $run;
    if (!is_array($input) || !isset($input['name'], $input['mode']) || !is_string($input['name'])) {
        throw new InvalidArgumentException('Fixture requires a name and declared mode.');
    }
    if ($input['mode'] === 'exit') { $run->finish('Request closed.'); }
    $answer = match ($input['mode']) {
        'status' => 'Status: pending.',
        'explain' => $run->generate(new Element('explanation', '', '', ['prompt' => 'Explain the pending status briefly.'])),
        default => throw new InvalidArgumentException('Unknown fixture mode.'),
    };
    // Bind data as parser variables, never concatenate untrusted text into source.
    $parser = new Parser($source);
    $parser->setVariables(['name' => $input['name'], 'answer' => $answer]);
    return $parser;
};
$trace = new TaskTrace('cortex-script');
$strategy = new ScriptStrategy('intake', '1', $source, $prepare, $approve, 'fixture-actor', $generator, 1, $trace);
$checks = [];
$checks['real_parser_interpolates'] = $strategy->predict(['name' => 'Ada', 'mode' => 'status']) === 'Hello Ada. Status: pending.';
$checks['deterministic_branch_skips_generation'] = $generator->calls === 0;
$firstRun = $strategy->lastResult()->toArray();
$checks['zero_is_preserved'] = $strategy->predict(['name' => '0', 'mode' => 'status']) === 'Hello 0. Status: pending.';
$checks['independent_runs'] = $firstRun['run_id'] !== $strategy->lastResult()->toArray()['run_id'];
$checks['hybrid_slot_composes'] = $strategy->predict(['name' => 'Ada', 'mode' => 'explain']) === 'Hello Ada. Your request is ready for review.';
$generated = $strategy->lastResult()->toArray();
$checks['slot_freshly_authorized'] = array_column($generated['authorizations'], 'operation') === ['execute', 'generate'];
$checks['completed_generation_receipt'] = $generated['generations'][0]['status'] === 'completed' && $generated['generation_calls'] === 1;
$checks['unknown_metrics_remain_unknown'] = $generated['cost'] === null && $generated['confidence'] === null;
$beforeExit = $generator->calls;
$checks['early_exit_skips_generation'] = $strategy->predict(['name' => 'Ada', 'mode' => 'exit']) === 'Request closed.' && $generator->calls === $beforeExit;
$checks['early_exit_is_explicit'] = $strategy->lastResult()->status() === 'early_exit';
try { $captured->generate(new Element('late', '', '')); $checks['closed_handle_denies_replay'] = false; }
catch (LogicException) { $checks['closed_handle_denies_replay'] = true; }
$denied = new ScriptStrategy('intake', '1', $source, $prepare, static fn () => new AutonomyDecision(), 'fixture-actor', $generator);
$checks['denial_has_no_output_or_generation'] = $denied->run(['name' => 'Ada', 'mode' => 'explain'])->status() === 'denied' && $generator->calls === $beforeExit;
$adapter = new ScriptRouteAdapter($strategy, sideEffectFree: true);
$request = new StrategyRouteRequest(['id' => 'script-route', 'subject_id' => 'fixture-actor',
    'capability_id' => 'intake.execute', 'capability_version' => '1', 'allowed_modes' => ['generative'],
    'candidates' => [['id' => 'intake', 'version' => '1']], 'input' => ['name' => 'Ada', 'mode' => 'status']]);
$route = (new StrategyRouter([$adapter]))->route($request, $approve($request->toArray()));
$checks['router_selects_versioned_script'] = $route->status === 'completed' && $route->output === 'Hello Ada. Status: pending.';
$checks['unknown_budget_not_treated_as_free'] = !$adapter->eligibility(new StrategyRouteRequest([
    'subject_id' => 'fixture-actor', 'limits' => ['max_cost' => 0]]))->eligible;
$intelligence = new Intelligence();
$intelligence->registerStrategy($strategy, 'script');
$checks['ordinary_intelligence_uses_script'] = $intelligence->predict(['name' => 'Ada', 'mode' => 'status']) === 'Hello Ada. Status: pending.';
$checks['trace_links_source_and_result'] = count($trace->toArray()['spans']) === 6 && $firstRun['source_sha256'] === hash('sha256', $source);
$passed = !Arr::has($checks, false, true);
echo json_encode(['experiment' => 'cortex-script-v1', 'passed' => $passed, 'checks' => $checks,
    'generated' => $generated, 'trace' => $trace->toArray(), 'limits' => [
        'Trusted host script preparation and parser registries; no sandbox or durable recovery.',
        'Generator fixture only; no provider controls, billing or retry qualification.',
        'Dispatch counts cover this adapter, not generator-internal retries or time/memory bounds.',
    ]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
