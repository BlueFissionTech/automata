<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\Telemetry\CpctHook;
use BlueFission\Automata\LLM\Agent\Telemetry\CpctPricing;
use BlueFission\Automata\LLM\Agent\Telemetry\CpctReport;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\LLM\Tools\BaseTool;
use BlueFission\Net\HTTP;
use BlueFission\Obj;
use PHPUnit\Framework\TestCase;

class CpctClientStub implements IClient
{
    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        $reply = new Reply();
        $reply->addMessage('generated', true);
        return $reply;
    }

    public function complete($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage('completed', true);
        return $reply;
    }

    public function respond($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage('responded', true);
        return $reply;
    }
}

class CpctEchoTool extends BaseTool
{
    public function __construct()
    {
        $this->name = 'echo';
        $this->description = 'Echo for telemetry tests.';
    }

    public function execute($input): string
    {
        return Arr::is($input) ? (string)HTTP::jsonEncode($input) : (string)$input;
    }
}

class AgentCpctTelemetryTest extends TestCase
{
    public function testAgentPropagatesTaskIdThroughToolSpan(): void
    {
        $agent = new Agent(new CpctClientStub());
        $agent->startTask('task-cpct-1', ['surface' => 'test']);
        $agent->registerTool('echo', new CpctEchoTool(), [
            'parallel_safe' => true,
            'input_schema' => [
                'type' => 'object',
                'required' => ['message'],
                'properties' => [
                    'message' => ['type' => 'string'],
                ],
            ],
        ]);

        $result = $agent->callTool('echo', ['message' => 'hello']);
        $trace = $agent->taskTrace()->toArray();

        $this->assertTrue($result->ok());
        $this->assertSame('task-cpct-1', $trace['task_id']);
        $this->assertCount(1, $trace['spans']);
        $this->assertSame('tool', $trace['spans'][0]['kind']);
        $this->assertSame('echo', $trace['spans'][0]['name']);
        $this->assertSame('completed', $trace['spans'][0]['status']);
        $this->assertTrue($trace['spans'][0]['batchable']);
    }

    public function testCpctReportBuildsDistributionAndBudgetFlags(): void
    {
        $traces = [
            $this->traceWithCost('task-a', 1.0),
            $this->traceWithCost('task-b', 2.0),
            $this->traceWithCost('task-c', 3.0),
        ];

        $report = CpctReport::build($traces, [], ['target_cost' => 2.5]);

        $this->assertSame(3, $report['task_count']);
        $this->assertSame(2.0, $report['cpct_distribution']['p50']);
        $this->assertSame(2.8, $report['cpct_distribution']['p90']);
        $this->assertFalse($report['tasks'][1]['over_budget']);
        $this->assertTrue($report['tasks'][2]['over_budget']);
    }

    public function testCpctReportTracksCacheBatchAndTierRoutingLines(): void
    {
        $trace = new TaskTrace('task-lines');
        $trace->addSpan((new TaskTraceSpan([
            'task_id' => 'task-lines',
            'kind' => TaskTraceSpan::KIND_MODEL,
            'name' => 'expensive-model-call',
            'model' => 'default',
            'input_tokens' => 1000,
            'output_tokens' => 250,
            'cache_hit_tokens' => 600,
            'cache_write_tokens' => 100,
            'uncached_input_tokens' => 300,
            'batch_tokens' => 200,
            'batchable' => true,
            'batch_processed' => true,
            'estimated_cost' => 3.0,
            'candidate_model' => 'cheap-model',
            'candidate_estimated_cost' => 1.5,
            'candidate_met_slo' => true,
        ]))->finish('completed'));
        $trace->addSpan((new TaskTraceSpan([
            'task_id' => 'task-lines',
            'kind' => TaskTraceSpan::KIND_MODEL,
            'name' => 'interactive-model-call',
            'model' => 'default',
            'batchable' => true,
            'estimated_cost' => 1.0,
        ]))->finish('completed'));
        $trace->complete('completed');

        $report = CpctReport::build([$trace], [
            'default' => [
                'input' => 1.0,
                'output' => 2.0,
                'cache_hit_input' => 0.1,
                'cache_write_input' => 1.0,
                'batch_multiplier' => 0.5,
            ],
        ]);

        $this->assertSame(600, $report['cache_roi']['cache_hit_tokens']);
        $this->assertSame(100, $report['cache_roi']['cache_write_tokens']);
        $this->assertSame(6.0, $report['cache_roi']['hit_to_write_ratio']);
        $this->assertSame(0.54, $report['cache_roi']['estimated_savings']);
        $this->assertSame(2, $report['batch_utilization']['batchable_spans']);
        $this->assertSame(1, $report['batch_utilization']['batched_spans']);
        $this->assertSame(0.5, $report['batch_utilization']['utilization']);
        $this->assertSame(1, $report['tier_routing']['candidate_spans']);
        $this->assertSame(1.5, $report['tier_routing']['estimated_savings']);
    }

    public function testTaskTraceCapturesProviderUsageAndRoutingHooks(): void
    {
        $trace = new TaskTrace('task-capture');
        $trace->recordModelUsage('provider-call', [
            'prompt_tokens' => 100,
            'completion_tokens' => 40,
            'cache_hit_tokens' => 25,
        ], [
            'provider' => 'example',
            'model' => 'default',
            'estimated_cost' => 0.25,
        ]);
        $trace->recordBatchUsage('nightly-eval', 200, true, ['model' => 'default']);
        $trace->recordRoutingCandidate('provider-call', 'cheap-model', 0.10, true);
        $trace->complete('completed');

        $report = CpctReport::build([$trace], [
            'default' => [
                'input' => 1.0,
                'output' => 2.0,
            ],
        ]);

        $this->assertInstanceOf(IDispatcher::class, $trace);
        $this->assertInstanceOf(Obj::class, $trace);
        $this->assertCount(2, $trace->spans());
        $this->assertSame(25, $report['cache_roi']['cache_hit_tokens']);
        $this->assertSame(1, $report['batch_utilization']['batched_spans']);
        $this->assertSame(1, $report['tier_routing']['candidate_spans']);
        $this->assertSame(0.15, $report['tier_routing']['estimated_savings']);
    }

    public function testTaskTraceSpanUsesObjectFields(): void
    {
        $span = new TaskTraceSpan([
            'task_id' => 'task-object',
            'name' => 'model-call',
            'batchable' => false,
            'metadata' => ['source' => 'test'],
        ]);

        $this->assertInstanceOf(Obj::class, $span);
        $this->assertSame('task-object', $span->get('task_id'));
        $this->assertFalse($span->get('batchable'));
        $this->assertSame('model-call', $span->field('name'));
    }

    public function testCpctPricingIsConfigurable(): void
    {
        $pricing = new CpctPricing([
            'default' => [
                'input' => 1.0,
                'output' => 2.0,
            ],
        ]);

        $pricing->config('models', [
            'default' => [
                'input' => 2.0,
                'output' => 4.0,
            ],
        ]);

        $this->assertSame(6.0, $pricing->costForSpan([
            'model' => 'default',
            'input_tokens' => 1000,
            'output_tokens' => 1000,
        ]));
    }

    public function testCpctHookConstantsExposeCapturePoints(): void
    {
        $this->assertContains(CpctHook::MODEL_USAGE_CAPTURED, CpctHook::all());
        $this->assertContains(CpctHook::BATCH_USAGE_CAPTURED, CpctHook::all());
        $this->assertContains(CpctHook::ROUTING_CANDIDATE_CAPTURED, CpctHook::all());
    }

    protected function traceWithCost(string $taskId, float $cost): TaskTrace
    {
        $trace = new TaskTrace($taskId);
        $trace->addSpan((new TaskTraceSpan([
            'task_id' => $taskId,
            'kind' => TaskTraceSpan::KIND_MODEL,
            'name' => 'model-call',
            'estimated_cost' => $cost,
        ]))->finish('completed'));
        $trace->complete('completed');

        return $trace;
    }
}
