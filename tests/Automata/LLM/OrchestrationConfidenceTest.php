<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent\Orchestration\{OrchestratedAgent, OrchestrationConfig, Orchestrator};
use PHPUnit\Framework\TestCase;

/** Worker completion is not evidence of measured confidence. */
final class OrchestrationConfidenceTest extends TestCase
{
    public function testMissingConfidenceStaysUnknownAcrossPatterns(): void
    {
        foreach ([OrchestrationConfig::SEQUENTIAL, OrchestrationConfig::FAN_OUT,
            OrchestrationConfig::HIERARCHICAL, OrchestrationConfig::PIANO] as $pattern) {
            $result = (new Orchestrator(['pattern' => $pattern,
                'workers' => ['bare' => static fn () => 'value',
                    'known' => static fn () => ['output' => 'measured', 'confidence' => 0.8]],
            ]))->run();
            $this->assertSame('completed', $result->status());
            $this->assertNull($result->confidence(), $pattern);
        }
    }

    public function testExplicitNullWorkerDefinitionDoesNotTriggerFallback(): void
    {
        $calls = 0;
        $run = static function ($confidence) use (&$calls) {
            return (new Orchestrator(['pattern' => OrchestrationConfig::HIERARCHICAL,
                'workers' => ['worker' => ['handler' => static fn () => 'value', 'confidence' => $confidence]],
                'fallback' => static function () use (&$calls) { ++$calls; return ['output' => 'fallback', 'confidence' => 0.9]; },
            ]))->run();
        };
        $unknown = $run(null);
        $this->assertNull($unknown->confidence());
        $this->assertSame(0, $calls);
        $run(0.0);
        $this->assertSame(1, $calls);
    }

    public function testNestedAgentPreservesUnknownConfidence(): void
    {
        $child = new OrchestratedAgent('child', ['workers' => [
            'one' => static fn () => ['output' => 'one', 'confidence' => null],
        ]]);
        $this->assertNull($child()['confidence']);
        $result = (new Orchestrator(['workers' => ['child' => $child->worker()]]))->run();
        $this->assertNull($result->confidence());
    }

    public function testOnlyFiniteNumericRatiosCanBeConfidenceEvidence(): void
    {
        foreach (['0.8', true, -0.1, 1.1, INF, NAN] as $invalid) {
            $result = (new Orchestrator(['workers' => [
                'invalid' => static fn () => ['output' => 'value', 'confidence' => $invalid],
            ]]))->run();
            $this->assertSame('failed', $result->status());
            $this->assertNull($result->confidence());
        }
        $known = (new Orchestrator(['workers' => [
            'low' => static fn () => ['output' => 'low', 'confidence' => 0.2],
            'high' => static fn () => ['output' => 'high', 'confidence' => 0.8],
        ]]))->run();
        $this->assertSame(0.5, $known->confidence());
    }
}
