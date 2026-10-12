<?php

declare(strict_types=1);

namespace BlueFission\Automata\Examples\Cortex;

use BlueFission\Automata\Response\ResponseComposer;
use InvalidArgumentException;
use LogicException;

/** Process-local stand-in for a host journal and receiver; not a durable store. */
final class HostReceiptFixture
{
    private array $checkpoints = [];
    private array $results = [];

    public function checkpoint(array $scope, int $expectedRevision, array $snapshot): int
    {
        $key = $this->key($scope);
        ResponseComposer::restore($snapshot);
        if (($snapshot['envelope']['trace']['trace_id'] ?? null) !== $scope['correlation_id']) {
            throw new InvalidArgumentException('Checkpoint correlation does not match host scope.');
        }
        $current = $this->checkpoints[$key] ?? null;
        if (($current['revision'] ?? 0) !== $expectedRevision) {
            throw new LogicException('Stale checkpoint revision.');
        }
        if ($current !== null && $current['scope'] !== $scope) {
            throw new LogicException('Checkpoint scope changed.');
        }
        $revision = $expectedRevision + 1;
        $this->checkpoints[$key] = ['scope' => $this->copy($scope), 'revision' => $revision,
            'snapshot' => $this->copy($snapshot)];
        return $revision;
    }

    public function load(array $scope): ?array
    {
        $record = $this->checkpoints[$this->key($scope)] ?? null;
        if ($record !== null && $record['scope'] !== $scope) {
            throw new LogicException('Checkpoint scope changed.');
        }
        return $record === null ? null : $this->copy($record);
    }

    /** A terminal receiver result may be recorded once; unknown is never success. */
    public function recordResult(array $scope, string $releaseId, string $fragmentId, array $receipt): bool
    {
        $this->requireRelease($scope, $releaseId, $fragmentId);
        $key = $this->key($scope) . "\0" . $releaseId . "\0" . $fragmentId;
        if ($releaseId === '' || $fragmentId === '' || str_contains($releaseId, "\0")
            || str_contains($fragmentId, "\0") || !is_bool($receipt['successful'] ?? null)
            || !is_array($receipt['evidence'] ?? null)
            || count($receipt) !== 2
            || !in_array($receipt['evidence']['outcome'] ?? null, ['succeeded', 'denied', 'failed', 'stopped'], true)
            || $receipt['successful'] !== ($receipt['evidence']['outcome'] === 'succeeded')) {
            throw new InvalidArgumentException('A verified terminal receiver result is required.');
        }
        $receipt = $this->copy($receipt);
        if (isset($this->results[$key])) {
            if ($this->results[$key] !== $receipt) {
                throw new LogicException('Conflicting receiver result.');
            }
            return false;
        }
        $this->results[$key] = $receipt;
        return true;
    }

    public function result(array $scope, string $releaseId, string $fragmentId): ?array
    {
        $this->requireRelease($scope, $releaseId, $fragmentId);
        $receipt = $this->results[$this->key($scope) . "\0" . $releaseId . "\0" . $fragmentId] ?? null;
        return $receipt === null ? null : $this->copy($receipt);
    }

    private function requireRelease(array $scope, string $releaseId, string $fragmentId): void
    {
        $record = $this->load($scope);
        if ($record === null) {
            throw new LogicException('Checkpoint must exist before receiver reconciliation.');
        }
        $release = ResponseComposer::restore($record['snapshot'])->state()['releases'][$releaseId]['release'] ?? null;
        if ($release === null || !in_array($fragmentId, array_column($release['fragments'], 'id'), true)) {
            throw new InvalidArgumentException('Receiver result must name a prepared release fragment.');
        }
    }

    private function key(array $scope): string
    {
        foreach (['tenant_id', 'principal_id', 'subject_id', 'attempt_id', 'capability', 'capability_version',
            'correlation_id', 'causation_id', 'source_revision'] as $field) {
            if (!is_string($scope[$field] ?? null) || $scope[$field] === ''
                || str_contains($scope[$field], "\0")) {
                throw new InvalidArgumentException('Missing host scope field: ' . $field);
            }
        }
        return $scope['tenant_id'] . "\0" . $scope['subject_id'] . "\0" . $scope['attempt_id'];
    }

    private function copy(array $value): array
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            true, 512, JSON_THROW_ON_ERROR);
    }
}
