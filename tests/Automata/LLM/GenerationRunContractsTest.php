<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Generation\GeneratedArtifact;
use BlueFission\Automata\LLM\Generation\GenerationDiagnostic;
use BlueFission\Automata\LLM\Generation\IGenerationRunner;
use BlueFission\Automata\LLM\Generation\GenerationRunRequest;
use BlueFission\Automata\LLM\Generation\GenerationRunResult;
use BlueFission\Automata\LLM\Generation\GenerationStatus;
use BlueFission\Automata\LLM\Generation\GenerationStep;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class GenerationRunContractsTest extends TestCase
{
    public function testRequestCreatesStableDefaultTraceIdentifiers(): void
    {
        $request = new GenerationRunRequest(['input' => ['prompt' => 'Draft release notes.']]);

        $this->assertNotSame('', $request->runId());
        $this->assertSame($request->runId(), $request->taskId());
        $this->assertSame($request->runId(), $request->field('correlation_id'));
        $this->assertSame($request->runId(), $request->field('trace_id'));
    }

    public function testRequestPreservesPolicyEvidenceAndCancellation(): void
    {
        $request = new GenerationRunRequest([
            'run_id' => 'run-42',
            'policy' => ['approval' => 'required'],
            'evidence' => [['source' => 'spec']],
            'cancelled' => true,
        ]);

        $this->assertSame(['approval' => 'required'], $request->policy());
        $this->assertSame([['source' => 'spec']], $request->evidence());
        $this->assertTrue($request->isCancelled());
    }

    public function testRequestTreatsMalformedDeadlineAsTimedOut(): void
    {
        $request = new GenerationRunRequest(['deadline' => 'not-a-date']);

        $this->assertTrue($request->isTimedOut(new DateTimeImmutable('2026-01-01T00:00:00Z')));
    }

    public function testStatusVocabularySeparatesTerminalStates(): void
    {
        $this->assertTrue(GenerationStatus::isKnown(GenerationStatus::RUNNING));
        $this->assertFalse(GenerationStatus::isTerminal(GenerationStatus::RUNNING));
        $this->assertTrue(GenerationStatus::isTerminal(GenerationStatus::PARTIAL));
        $this->assertFalse(GenerationStatus::isKnown('unknown'));
    }

    public function testArtifactSupportsInlineAndReferencedOutputs(): void
    {
        $inline = new GeneratedArtifact([
            'artifact_id' => 'artifact-inline',
            'content' => 'Generated text',
            'evidence' => [['source' => 'model-output']],
        ]);
        $referenced = new GeneratedArtifact([
            'artifact_id' => 'artifact-ref',
            'reference' => 'artifact://release-notes/42',
        ]);

        $this->assertTrue($inline->isInline());
        $this->assertSame([['source' => 'model-output']], $inline->evidence());
        $this->assertFalse($referenced->isInline());
        $this->assertSame('artifact://release-notes/42', $referenced->reference());
    }

    public function testDiagnosticCarriesRetryAndEvidenceWithoutThrowing(): void
    {
        $diagnostic = new GenerationDiagnostic([
            'code' => 'provider_timeout',
            'message' => 'Provider did not complete in time.',
            'retryable' => true,
            'evidence' => [['attempt' => 2]],
        ]);

        $this->assertSame('provider_timeout', $diagnostic->code());
        $this->assertTrue($diagnostic->isError());
        $this->assertTrue($diagnostic->retryable());
        $this->assertSame([['attempt' => 2]], $diagnostic->evidence());
    }

    public function testCompletedResultSerializesTypedStepsAndArtifacts(): void
    {
        $request = new GenerationRunRequest([
            'run_id' => 'run-complete',
            'causation_id' => 'request-7',
            'policy' => ['publish' => false],
        ]);
        $artifact = new GeneratedArtifact([
            'artifact_id' => 'draft-1',
            'kind' => 'document',
            'content' => '# Draft',
        ]);
        $step = new GenerationStep([
            'step_id' => 'step-1',
            'status' => GenerationStatus::COMPLETED,
            'output_artifact_ids' => ['draft-1'],
        ]);

        $result = GenerationRunResult::completed($request, [$artifact], [$step], [
            'usage' => ['total_tokens' => 120],
        ]);
        $data = $result->toArray();

        $this->assertTrue($result->isSuccessful());
        $this->assertTrue($result->isTerminal());
        $this->assertSame('request-7', $data['causation_id']);
        $this->assertSame('step-1', $data['steps'][0]['step_id']);
        $this->assertSame('# Draft', $data['artifacts'][0]['content']);
        $this->assertSame(['publish' => false], $data['policy']);
        $this->assertSame(120, $data['usage']['total_tokens']);
    }

    public function testPartialResultPreservesUsableArtifactsAndDiagnostics(): void
    {
        $request = new GenerationRunRequest(['run_id' => 'run-partial']);
        $result = GenerationRunResult::partial(
            $request,
            [new GeneratedArtifact(['artifact_id' => 'usable', 'content' => 'Partial draft'])],
            [new GenerationDiagnostic(['code' => 'budget_exhausted', 'retryable' => false])],
            [],
            ['continuation' => ['cursor' => 'section-4']]
        );

        $this->assertSame(GenerationStatus::PARTIAL, $result->status());
        $this->assertFalse($result->isSuccessful());
        $this->assertCount(1, $result->artifacts());
        $this->assertSame('budget_exhausted', $result->diagnostics()[0]->code());
        $this->assertSame(['cursor' => 'section-4'], $result->toArray()['continuation']);
    }

    public function testFactoryMetadataCannotOverrideRequestIdentityOrOutcome(): void
    {
        $request = new GenerationRunRequest([
            'run_id' => 'canonical-run',
            'policy' => ['publish' => false],
            'evidence' => [['source' => 'request']],
        ]);

        $result = GenerationRunResult::completed($request, [], [], [
            'run_id' => 'spoofed-run',
            'status' => GenerationStatus::FAILED,
            'policy' => ['publish' => true],
            'evidence' => [],
        ]);
        $data = $result->toArray();

        $this->assertSame('canonical-run', $data['run_id']);
        $this->assertSame(GenerationStatus::COMPLETED, $data['status']);
        $this->assertSame(['publish' => false], $data['policy']);
        $this->assertSame([['source' => 'request']], $data['evidence']);
    }

    public function testFailureCancellationAndTimeoutRemainDistinct(): void
    {
        $request = new GenerationRunRequest(['run_id' => 'run-terminal']);
        $failed = GenerationRunResult::failed($request, [
            new GenerationDiagnostic(['code' => 'invalid_output']),
        ]);

        $this->assertSame(GenerationStatus::FAILED, $failed->status());
        $this->assertSame(GenerationStatus::CANCELLED, GenerationRunResult::cancelled($request)->status());
        $this->assertSame(GenerationStatus::TIMED_OUT, GenerationRunResult::timedOut($request)->status());
    }

    public function testArrayPayloadsRehydrateAsTypedValues(): void
    {
        $result = new GenerationRunResult([
            'steps' => [[
                'step_id' => 'step-array',
                'diagnostics' => [['code' => 'warning', 'severity' => GenerationDiagnostic::WARNING]],
            ]],
            'artifacts' => [['artifact_id' => 'artifact-array']],
            'diagnostics' => [['code' => 'result-warning', 'severity' => GenerationDiagnostic::WARNING]],
        ]);

        $this->assertInstanceOf(GenerationStep::class, $result->steps()[0]);
        $this->assertInstanceOf(GenerationDiagnostic::class, $result->steps()[0]->diagnostics()[0]);
        $this->assertInstanceOf(GeneratedArtifact::class, $result->artifacts()[0]);
        $this->assertFalse($result->diagnostics()[0]->isError());
    }

    public function testRunnerInterfaceKeepsExecutionBehindAnAdapter(): void
    {
        $runner = new class implements IGenerationRunner {
            public function run(GenerationRunRequest $request): GenerationRunResult
            {
                return GenerationRunResult::completed($request, [
                    new GeneratedArtifact(['artifact_id' => 'adapter-output', 'content' => 'done']),
                ]);
            }
        };

        $result = $runner->run(new GenerationRunRequest(['run_id' => 'adapter-run']));

        $this->assertSame('adapter-run', $result->toArray()['run_id']);
        $this->assertSame('done', $result->artifacts()[0]->content());
    }
}
