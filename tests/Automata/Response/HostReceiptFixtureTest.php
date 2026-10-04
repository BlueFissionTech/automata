<?php

declare(strict_types=1);

namespace BlueFission\Tests\Automata\Response;

require_once dirname(__DIR__, 3) . '/examples/generic/cortex/HostReceiptFixture.php';

use BlueFission\Automata\Examples\Cortex\HostReceiptFixture;
use BlueFission\Automata\Response\ResponseComposer;
use BlueFission\Automata\Response\ResponseEnvelope;
use BlueFission\Automata\Response\ResponseFragment;
use BlueFission\Automata\Response\ResponsePolicy;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class HostReceiptFixtureTest extends TestCase
{
    private function scope(): array
    {
        return ['tenant_id' => 'tenant-1', 'principal_id' => 'principal-1',
            'subject_id' => 'subject-1', 'attempt_id' => 'attempt-1',
            'capability' => 'fixture-tool', 'capability_version' => 'v1',
            'correlation_id' => 'trace-1', 'causation_id' => 'request-1',
            'source_revision' => 'source-1'];
    }

    private function composer(array $fragments, float $threshold = 1.0): ResponseComposer
    {
        return new ResponseComposer(new ResponseEnvelope('response-1', array_map(
            static fn (array $row): ResponseFragment => new ResponseFragment($row), $fragments),
            ['trace_id' => 'trace-1']), new ResponsePolicy([
                'threshold' => $threshold, 'minimum_threshold' => $threshold,
            ]));
    }

    public function testLostAcknowledgementReconcilesWithoutRepeatingReceiverEffect(): void
    {
        $scope = $this->scope();
        $journal = new HostReceiptFixture();
        $composer = $this->composer([
            ['id' => 'action', 'channel' => 'tool'],
            ['id' => 'confirmation', 'channel' => 'text', 'dependencies' => ['action']],
        ])->resolve('action', 'fixture')->resolve('confirmation', 'done');
        $release = $composer->prepare(1);
        $this->assertSame(1, $journal->checkpoint($scope, 0, $composer->snapshot()));
        $receipt = ['successful' => true, 'evidence' => ['outcome' => 'succeeded']];
        $this->assertTrue($journal->recordResult($scope, $release['id'], 'action', $receipt));
        unset($composer);

        $record = $journal->load($scope);
        $restored = ResponseComposer::restore($record['snapshot']);
        $this->assertSame($release, $restored->prepare(2));
        $this->assertFalse($journal->recordResult($scope, $release['id'], 'action', $receipt));
        $this->assertTrue($restored->acknowledge($release['id'], [
            'action' => $journal->result($scope, $release['id'], 'action'),
        ]));
        $this->assertSame(2, $journal->checkpoint($scope, $record['revision'], $restored->snapshot()));
        $this->assertSame('confirmation', $restored->prepare(3)['fragments'][0]['id']);
    }

    public function testStaleWriterAndChangedScopeCannotClaimCheckpoint(): void
    {
        $scope = $this->scope();
        $journal = new HostReceiptFixture();
        $composer = $this->composer([['id' => 'text', 'channel' => 'text']]);
        $journal->checkpoint($scope, 0, $composer->snapshot());
        $journal->checkpoint($scope, 1, $composer->snapshot());
        try {
            $journal->checkpoint($scope, 1, $composer->snapshot());
            $this->fail('Expected stale writer rejection.');
        } catch (LogicException $error) {
            $this->assertSame('Stale checkpoint revision.', $error->getMessage());
        }
        $wrong = [...$scope, 'capability_version' => 'v2'];
        $this->expectException(LogicException::class);
        $journal->load($wrong);
    }

    public function testCheckpointRequiresValidatedSnapshotAndMatchingCorrelation(): void
    {
        $scope = $this->scope();
        $journal = new HostReceiptFixture();
        $snapshot = $this->composer([['id' => 'text', 'channel' => 'text']])->snapshot();
        try {
            $journal->checkpoint($scope, 0, [...$snapshot, 'events' => [['type' => 'invented']]]);
            $this->fail('Expected malformed checkpoint rejection.');
        } catch (InvalidArgumentException $error) {
            $this->assertNull($journal->load($scope));
        }
        $this->expectException(InvalidArgumentException::class);
        $journal->checkpoint([...$scope, 'correlation_id' => 'other-trace'], 0, $snapshot);
    }

    public function testDuplicateConflictingAndUnknownResultsFailClosed(): void
    {
        $scope = $this->scope();
        $journal = new HostReceiptFixture();
        $composer = $this->composer([['id' => 'action', 'channel' => 'tool']])->resolve('action', 'fixture');
        $release = $composer->prepare(1);
        $journal->checkpoint($scope, 0, $composer->snapshot());
        $success = ['successful' => true, 'evidence' => ['outcome' => 'succeeded']];
        $this->assertTrue($journal->recordResult($scope, $release['id'], 'action', $success));
        $this->assertFalse($journal->recordResult($scope, $release['id'], 'action', $success));
        try {
            $journal->recordResult($scope, $release['id'], 'action',
                ['successful' => false, 'evidence' => ['outcome' => 'failed']]);
            $this->fail('Expected conflicting result rejection.');
        } catch (LogicException $error) {
            $this->assertSame('Conflicting receiver result.', $error->getMessage());
        }
        $this->assertSame($success, $journal->result($scope, $release['id'], 'action'));
        $this->expectException(InvalidArgumentException::class);
        $journal->recordResult($scope, $release['id'], 'action',
            ['successful' => true, 'evidence' => ['outcome' => 'unknown']]);
    }

    public function testPartialDeliveryDoesNotAcknowledgeAndDenialBlocksDependency(): void
    {
        $scope = $this->scope();
        $journal = new HostReceiptFixture();
        $composer = $this->composer([
            ['id' => 'first', 'channel' => 'tool'], ['id' => 'second', 'channel' => 'tool'],
            ['id' => 'confirmation', 'channel' => 'text', 'dependencies' => ['first', 'second']],
        ])->resolve('first', 'first')->resolve('second', 'second')->resolve('confirmation', 'done');
        $release = $composer->prepare(1);
        $journal->checkpoint($scope, 0, $composer->snapshot());
        $journal->recordResult($scope, $release['id'], 'first',
            ['successful' => false, 'evidence' => ['outcome' => 'denied']]);
        $this->assertNull($journal->result($scope, $release['id'], 'second'));
        $this->assertSame($release, ResponseComposer::restore($journal->load($scope)['snapshot'])->prepare(2));
        $this->assertSame($release['id'], $composer->state()['pending_release_id']);
        $journal->recordResult($scope, $release['id'], 'second',
            ['successful' => true, 'evidence' => ['outcome' => 'succeeded']]);
        $composer->acknowledge($release['id'], [
            'first' => $journal->result($scope, $release['id'], 'first'),
            'second' => $journal->result($scope, $release['id'], 'second'),
        ]);
        $this->assertNull($composer->prepare(3));
    }

    public function testCancellationStopsNewReleaseButExistingReceiptCanBeReconciled(): void
    {
        $scope = $this->scope();
        $journal = new HostReceiptFixture();
        $composer = $this->composer([
            ['id' => 'first', 'channel' => 'tool'], ['id' => 'later', 'channel' => 'text'],
        ], 0.5)->resolve('first', 'fixture');
        $release = $composer->prepare(1);
        $composer->cancel('caller_cancelled');
        $journal->checkpoint($scope, 0, $composer->snapshot());
        $this->assertNull($composer->prepare(2));
        $this->assertSame('cancelled', $composer->state()['fragments']['later']['status']);
        $journal->recordResult($scope, $release['id'], 'first',
            ['successful' => false, 'evidence' => ['outcome' => 'stopped']]);
        $restored = ResponseComposer::restore($journal->load($scope)['snapshot']);
        $this->assertTrue($restored->acknowledge($release['id'], [
            'first' => $journal->result($scope, $release['id'], 'first'),
        ]));
        $this->assertNull($restored->prepare(3));
    }
}
