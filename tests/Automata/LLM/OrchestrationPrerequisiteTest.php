<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent\Orchestration\{OrchestratedAgent, Orchestrator};
use PHPUnit\Framework\TestCase;

/** A receipt's presence cannot substitute for successful prerequisite status. */
final class OrchestrationPrerequisiteTest extends TestCase
{
    public function testSequentialStopsAndRetainsNoncompletedReceipts(): void
    {
        foreach (['failed', 'uncertain', 'pending', 'partial', 'cancelled'] as $status) {
            $calls = [];
            $result = (new Orchestrator(['workers' => [
                'prerequisite' => static function () use (&$calls, $status): array {
                    $calls[] = 'prerequisite';
                    return ['status' => $status, 'output' => ['receipt' => 'observed']];
                },
                'dependent' => static function () use (&$calls): string {
                    $calls[] = 'dependent';
                    return 'must not execute';
                },
            ]]))->run()->toArray();
            $this->assertSame(['prerequisite'], $calls, $status);
            $this->assertSame($status, $result['status']);
            $this->assertSame('observed', $result['worker_results'][0]['output']['receipt']);
            $this->assertSame(['dependent'], $result['metadata']['skipped_workers']);
            $this->assertArrayNotHasKey('dependent', $result['output']);
        }
    }

    public function testVerifierRequiresBooleanTrue(): void
    {
        foreach ([false, 'false', 'true', 1, 0, null, [], new \stdClass()] as $value) {
            $result = (new Orchestrator(['pattern' => 'reflexive', 'max_iterations' => 1,
                'producer' => static fn () => ['output' => 'draft'],
                'verifier' => static fn () => ['output' => ['passed' => $value]],
            ]))->run();
            $this->assertSame('failed', $result->status());
            $this->assertFalse($result->toArray()['metadata']['passed']);
        }
    }

    public function testUncertainProducerOrVerifierDoesNotRetryOrCertify(): void
    {
        foreach (['producer', 'verifier'] as $uncertainWorker) {
            $calls = [];
            $result = (new Orchestrator(['pattern' => 'reflexive', 'max_iterations' => 3,
                'producer' => static function () use (&$calls, $uncertainWorker): array {
                    $calls[] = 'producer';
                    return ['status' => $uncertainWorker === 'producer' ? 'uncertain' : 'completed',
                        'output' => 'draft'];
                },
                'verifier' => static function () use (&$calls): array {
                    $calls[] = 'verifier';
                    return ['status' => 'uncertain', 'output' => ['passed' => true]];
                },
            ]))->run()->toArray();
            $this->assertSame($uncertainWorker === 'producer' ? ['producer'] : ['producer', 'verifier'], $calls);
            $this->assertSame('uncertain', $result['status']);
            $this->assertFalse($result['metadata']['passed']);
            $this->assertSame(1, $result['iterations']);
        }
    }

    public function testNestedUncertaintyStopsParentDependent(): void
    {
        $calls = 0;
        $child = new OrchestratedAgent('child', ['workers' => [
            'one' => static fn () => ['status' => 'uncertain', 'output' => 'receipt'],
        ]]);
        $parent = (new Orchestrator(['workers' => [
            'child' => $child,
            'dependent' => static function () use (&$calls): string { ++$calls; return 'unexpected'; },
        ]]))->run();
        $this->assertSame('uncertain', $parent->status());
        $this->assertSame(0, $calls);
    }

    public function testGenuineRejectionCanReviseAndBooleanAcceptanceCompletes(): void
    {
        $result = (new Orchestrator(['pattern' => 'reflexive', 'max_iterations' => 2,
            'producer' => static fn ($context) => ['output' => $context['feedback'] ?? 'draft'],
            'verifier' => static fn ($context) => ['output' => [
                'passed' => $context['output'] === 'revised', 'feedback' => 'revised',
            ]],
        ]))->run()->toArray();
        $this->assertSame('completed', $result['status']);
        $this->assertSame('revised', $result['output']);
        $this->assertSame(2, $result['iterations']);
    }
}
