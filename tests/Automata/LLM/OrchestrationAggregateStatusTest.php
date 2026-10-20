<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent\Orchestration\{OrchestratedAgent, Orchestrator};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Aggregate completion must describe every required result, not just dispatch. */
final class OrchestrationAggregateStatusTest extends TestCase
{
    /** Exercise both worker orders so a successful last peer cannot hide a failure. */
    public static function peerOutcomes(): iterable
    {
        $cases = [
            ['completed', 'completed'], ['failed', 'partial'],
            ['rejected', 'partial'], ['timed_out', 'partial'],
            ['cancelled', 'partial'], ['pending', 'partial'],
            ['accepted', 'partial'], ['in_progress', 'partial'],
            ['partial', 'partial'], ['uncertain', 'uncertain'],
            ['future_status', 'uncertain'], [null, 'uncertain'],
            [false, 'uncertain'], [23, 'uncertain'],
        ];
        foreach (['fan_out', 'hierarchical', 'piano'] as $pattern) {
            foreach ($cases as $index => [$status, $expected]) {
                foreach ([false, true] as $reverse) {
                    yield "$pattern/$index/" . (int)$reverse => [$pattern, $status, $expected, $reverse];
                }
            }
        }
    }

    /** Preserve independently observed execution, receipt, status and confidence. */
    #[DataProvider('peerOutcomes')]
    public function testMixedPeers(string $pattern, mixed $status, string $expected, bool $reverse): void
    {
        $calls = [];
        $workers = [];
        $names = $reverse ? ['completed_peer', 'subject'] : ['subject', 'completed_peer'];
        foreach ($names as $name) {
            $workers[$name] = static function () use (&$calls, $name, $status): array {
                $calls[] = $name;
                return ['status' => $name === 'subject' ? $status : 'completed',
                    'output' => ['receipt_' . $name => $name], 'confidence' => null];
            };
        }
        $result = (new Orchestrator(['pattern' => $pattern, 'workers' => $workers]))->run()->toArray();
        $this->assertSame($names, $calls);
        $this->assertSame($expected, $result['status']);
        $this->assertNull($result['confidence']);
        foreach ($result['worker_results'] as $worker) {
            if ($worker['name'] !== 'subject') { continue; }
            $this->assertSame($status, $worker['status']);
            $this->assertSame('subject', $worker['output']['receipt_subject']);
        }
        $this->assertSame('subject', $result['output']['receipt_subject']);
        $this->assertSame('completed_peer', $result['output']['receipt_completed_peer']);
    }

    /** Homogeneous groups distinguish active, partial, failed and unknown work. */
    public function testHomogeneousAndEmptyGroups(): void
    {
        foreach (['fan_out', 'hierarchical', 'piano'] as $pattern) {
            foreach (['completed' => 'completed', 'failed' => 'failed', 'cancelled' => 'failed',
                'pending' => 'in_progress', 'accepted' => 'in_progress',
                'in_progress' => 'in_progress', 'partial' => 'partial',
                'uncertain' => 'uncertain', 'future_status' => 'uncertain'] as $status => $expected) {
                $worker = static fn (): array => ['status' => $status, 'output' => 'receipt'];
                $result = (new Orchestrator(['pattern' => $pattern,
                    'workers' => ['one' => $worker, 'two' => $worker]]))->run();
                $this->assertSame($expected, $result->status(), "$pattern/$status");
            }
            $this->assertSame('completed', (new Orchestrator(['pattern' => $pattern]))->run()->status());
        }
    }

    /** A controller which cannot authorize dispatch must not run any child. */
    public function testBlockedControllersNeverDispatch(): void
    {
        foreach (['hierarchical', 'piano'] as $pattern) {
            foreach (['failed', 'rejected', 'uncertain', 'pending', 'future_status', null] as $status) {
                $calls = 0;
                $result = (new Orchestrator(['pattern' => $pattern,
                    'supervisor' => static fn (): array => ['status' => $status,
                        'output' => ['workers' => ['child'], 'receipt' => 'controller']],
                    'workers' => ['child' => static function () use (&$calls): string {
                        ++$calls;
                        return 'unexpected';
                    }],
                ]))->run()->toArray();
                $this->assertSame(0, $calls, $pattern);
                $this->assertNotSame('completed', $result['status']);
                $this->assertSame($status, $result['worker_results'][0]['status']);
                $this->assertSame('controller', $result['worker_results'][0]['output']['receipt']);
            }
        }
    }

    /** Accepted planning is a dispatch decision, not an incomplete child result. */
    public function testAcceptedControllersCanRunCompletedChildren(): void
    {
        foreach (['hierarchical', 'piano'] as $pattern) {
            $result = (new Orchestrator(['pattern' => $pattern,
                'supervisor' => static fn (): array => ['status' => 'accepted',
                    'output' => ['workers' => ['child']]],
                'workers' => ['child' => static fn (): string => 'done'],
            ]))->run();
            $this->assertSame('completed', $result->status());
        }
    }

    /** Neither a fallback nor a parent may erase an unresolved effect receipt. */
    public function testUncertaintySurvivesFallbackAndNestedPrerequisites(): void
    {
        $child = new OrchestratedAgent('child', ['pattern' => 'hierarchical',
            'workers' => ['one' => static fn (): array => ['status' => 'uncertain',
                'output' => ['receipt' => 'effect_pending'], 'confidence' => 0.1]],
            'fallback' => static fn (): array => ['output' => ['proposal' => 'replacement']],
        ]);
        $calls = 0;
        $result = (new Orchestrator(['workers' => ['child' => $child,
            'dependent' => static function () use (&$calls): string { ++$calls; return 'unexpected'; },
        ]]))->run()->toArray();
        $this->assertSame('uncertain', $result['status']);
        $this->assertSame(0, $calls);
        $this->assertSame(['dependent'], $result['metadata']['skipped_workers']);
    }
}
