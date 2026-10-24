<?php

namespace BlueFission\Tests\Automata\Cortex;

use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Examples\Cortex\CompoundCortex;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../examples/generic/cortex/CompoundCortex.php';

/** Inspect actual execution receipts, independently of the fixture's quality score. */
final class CompoundAuthorizationReceiptTest extends TestCase
{
    /** Every dispatched sensory, enrichment and response node must report its grant. */
    public function testApprovedAttemptsRetainAllowedCodes(): void
    {
        $run = (new CompoundCortex())->run('approved-receipts', $this->input());
        $count = 0;
        foreach ($run['passes'] as $pass) {
            foreach ($pass['result']['nodes'] as $node) {
                foreach ($node['attempts'] as $attempt) {
                    $this->assertTrue($attempt['authorization']['allowed']);
                    $this->assertSame(AutonomyDecision::CODE_ALLOWED, $attempt['authorization']['code']);
                    ++$count;
                }
            }
        }
        $this->assertSame(8, $count);
        $this->assertSame(1, $run['response_calls']);
    }

    /** A denied response preserves a denial receipt without dispatching its worker. */
    public function testDeniedResponseRetainsDenialCodeAndDoesNotDispatch(): void
    {
        $run = (new CompoundCortex())->run('denied-receipts', $this->input(), allowResponse: false);
        $attempts = $run['passes'][2]['result']['nodes']['answer']['attempts'];
        $this->assertCount(1, $attempts);
        $this->assertFalse($attempts[0]['authorization']['allowed']);
        $this->assertSame(AutonomyDecision::CODE_CAPABILITY_NOT_GRANTED, $attempts[0]['authorization']['code']);
        $this->assertSame(0, $run['response_calls']);
        $this->assertNull($run['selected']);
        $this->assertNull($run['output']);
    }

    /** Synthetic compound input activates both enrichment branches and their join. */
    private function input(): array
    {
        return ['segments' => [
            ['type' => 'text', 'payload' => 'help inspect signal', 'meta' => ['mime' => 'text/plain']],
            ['type' => 'video', 'payload' => [1, 0, 1, 1], 'meta' => ['mime' => 'video/example-binary']],
        ]];
    }
}
