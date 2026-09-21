<?php

namespace BlueFission\Automata\Strategy\Workflow;

use BlueFission\Arr;
use BlueFission\Automata\Path\{Graph, Node};
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;

/** Immutable, bounded proposal captured from the shared graph implementation. */
final class StrategyWorkflow
{
    private array $record;

    public function __construct(string $id, string $version, Graph $graph, array $outputs, ?int $minimumOutputs = null)
    {
        RecordSnapshot::identifier($id, 'workflow id');
        RecordSnapshot::identifier($version, 'workflow version');
        $outputCount = Arr::make($outputs)->count();
        if ($outputCount > 128) { throw new InvalidArgumentException('Too many output nodes.'); }
        $outputs = RecordSnapshot::copy($outputs);
        $nodes = $graph->nodes();
        $nodeCount = Arr::make($nodes)->count();
        if ($nodeCount < 1 || $nodeCount > 128) { throw new InvalidArgumentException('Workflows require 1..128 nodes.'); }
        $configs = [];
        $edges = [];
        foreach ($nodes as $node) {
            $name = $node->getName();
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,127}$/D', $name)) { throw new InvalidArgumentException('Invalid workflow node id.'); }
            if (!isset($nodes[$name]) || $nodes[$name] !== $node || isset($configs[$name])) { throw new InvalidArgumentException('Graph node identity is inconsistent.'); }
            $config = RecordSnapshot::copy($node->meta['workflow'] ?? null);
            if (!is_array($config)) { throw new InvalidArgumentException('Nodes require workflow metadata.'); }
            self::keys($config, ['strategy_id', 'strategy_version', 'capability_id', 'capability_version', 'allowed_modes', 'limits', 'join', 'maximum_attempts', 'retry_codes']);
            foreach (['strategy_id', 'strategy_version', 'capability_id', 'capability_version'] as $field) { RecordSnapshot::identifier($config[$field] ?? null, $field); }
            $config += ['allowed_modes' => ['deterministic'], 'limits' => [], 'join' => 'all', 'maximum_attempts' => 1, 'retry_codes' => []];
            if (!is_array($config['allowed_modes']) || !array_is_list($config['allowed_modes']) || $config['allowed_modes'] === []) { throw new InvalidArgumentException('Invalid allowed strategy modes.'); }
            foreach ($config['allowed_modes'] as $mode) {
                if (!Arr::make(['deterministic', 'learned', 'generative'])->has($mode, true)) { throw new InvalidArgumentException('Invalid allowed strategy mode.'); }
            }
            if (!Arr::make(['all', 'any'])->has($config['join'], true) || !is_int($config['maximum_attempts'])
                || $config['maximum_attempts'] < 1 || $config['maximum_attempts'] > 8 || !is_array($config['retry_codes']) || !array_is_list($config['retry_codes'])) { throw new InvalidArgumentException('Invalid join or retry bounds.'); }
            foreach ($config['retry_codes'] as $code) { RecordSnapshot::identifier($code, 'retry code'); }
            if (!is_array($config['limits'])) { throw new InvalidArgumentException('Node limits must be a map.'); }
            self::keys($config['limits'], ['max_cost', 'max_latency_ms', 'max_energy', 'max_invocations']);
            foreach ($config['limits'] as $key => $value) { RecordSnapshot::number($value, $key); }
            $configs[$name] = $config;
            foreach ($graph->neighbors($name) as $to) {
                if (!isset($nodes[$to])) { throw new InvalidArgumentException('Unknown dependency node.'); }
                $edge = RecordSnapshot::copy($graph->edgeAttributes($name, $to));
                self::keys($edge, ['on', 'when']);
                $edge += ['on' => 'completed'];
                if (!Arr::make(['completed', 'failed'])->has($edge['on'], true)) { throw new InvalidArgumentException('Invalid edge status.'); }
                if (Arr::make($edge)->hasKey('when')) {
                    $when = $edge['when'];
                    if (!is_array($when)) { throw new InvalidArgumentException('Invalid edge condition.'); }
                    self::keys($when, ['path', 'equals']);
                    if (!isset($when['path']) || !is_array($when['path']) || !array_is_list($when['path']) || Arr::make($when['path'])->count() > 16 || !Arr::make($when)->hasKey('equals')) { throw new InvalidArgumentException('Condition requires path and equals.'); }
                    foreach ($when['path'] as $key) {
                        if (!is_int($key) && !is_string($key)) { throw new InvalidArgumentException('Invalid condition path.'); }
                    }
                }
                $edges[] = ['from' => $name, 'to' => $to, ...$edge];
                if (Arr::make($edges)->count() > 512) { throw new InvalidArgumentException('Workflows allow at most 512 edges.'); }
            }
        }
        $degrees = Arr::make($configs)->map(static fn (): int => 0)->val();
        foreach ($edges as $edge) { ++$degrees[$edge['to']]; }
        $queue = Arr::make($degrees)->filter(static fn ($degree) => $degree === 0)->keys();
        $visited = 0;
        while ($queue->count() > 0) {
            $from = $queue->shift();
            ++$visited;
            foreach ($edges as $edge) { if ($edge['from'] === $from && --$degrees[$edge['to']] === 0) { $queue[] = $edge['to']; } }
        }
        if ($visited !== Arr::make($configs)->count()) { throw new InvalidArgumentException('Workflow cycles are not permitted; use bounded node retries.'); }
        // Keep SORT_REGULAR at this validation boundary: Arr::unique() uses
        // SORT_STRING, which can coerce malformed values before they are rejected.
        if (!array_is_list($outputs) || $outputs === [] || Arr::make(array_unique($outputs, SORT_REGULAR))->count() !== $outputCount) { throw new InvalidArgumentException('Output nodes must be a unique nonempty list.'); }
        foreach ($outputs as $output) { if (!is_string($output) || !isset($configs[$output])) { throw new InvalidArgumentException('Unknown output node.'); } }
        $minimumOutputs ??= $outputCount;
        if ($minimumOutputs < 1 || $minimumOutputs > $outputCount) { throw new InvalidArgumentException('Invalid output completion threshold.'); }
        $this->record = RecordSnapshot::copy(['schema_version' => 1, 'id' => $id, 'version' => $version,
            'nodes' => Arr::make($configs)->map(static fn ($config, $name) => ['id' => $name, 'config' => $config])->values()->val(),
            'edges' => $edges, 'outputs' => $outputs, 'minimum_outputs' => $minimumOutputs]);
    }

    public function toArray(): array { return $this->record; }

    /** Imports proposals only, never router bindings, authorization or live execution state. */
    public static function fromArray(array $record): self
    {
        self::keys($record, ['schema_version', 'id', 'version', 'nodes', 'edges', 'outputs', 'minimum_outputs']);
        if (($record['schema_version'] ?? null) !== 1 || !is_array($record['nodes'] ?? null) || !array_is_list($record['nodes'])
            || Arr::make($record['nodes'])->count() > 128 || !is_array($record['edges'] ?? null) || !array_is_list($record['edges'])
            || Arr::make($record['edges'])->count() > 512 || !is_array($record['outputs'] ?? null) || !is_int($record['minimum_outputs'] ?? null)) { throw new InvalidArgumentException('Invalid workflow record.'); }
        $record = RecordSnapshot::copy($record);
        RecordSnapshot::identifier($record['id'] ?? null, 'workflow id');
        RecordSnapshot::identifier($record['version'] ?? null, 'workflow version');
        $graph = new Graph();
        $seen = [];
        foreach ($record['nodes'] as $node) {
            if (!is_array($node)) { throw new InvalidArgumentException('Invalid node record.'); }
            self::keys($node, ['id', 'config']);
            $id = RecordSnapshot::identifier($node['id'] ?? null, 'node id');
            if (isset($seen[$id]) || !is_array($node['config'] ?? null)) { throw new InvalidArgumentException('Duplicate or malformed node.'); }
            $seen[$id] = true;
            $graph->addNode(new Node($id, null, [], ['workflow' => $node['config']]));
        }
        foreach ($record['edges'] as $edge) {
            if (!is_array($edge)) { throw new InvalidArgumentException('Invalid edge record.'); }
            self::keys($edge, ['from', 'to', 'on', 'when']);
            $from = RecordSnapshot::identifier($edge['from'] ?? null, 'edge source');
            $to = RecordSnapshot::identifier($edge['to'] ?? null, 'edge target');
            if (!isset($seen[$from], $seen[$to]) || $graph->edgeAttributes($from, $to) !== null) { throw new InvalidArgumentException('Unknown or duplicate edge.'); }
            unset($edge['from'], $edge['to']);
            $graph->connect($from, $to, $edge);
        }
        $plan = new self($record['id'], $record['version'], $graph, $record['outputs'], $record['minimum_outputs']);
        if (RecordSnapshot::fingerprint($plan->toArray()) !== RecordSnapshot::fingerprint($record)) { throw new InvalidArgumentException('Workflow import must preserve the canonical exported plan.'); }
        return $plan;
    }

    private static function keys(array $record, array $allowed): void
    {
        if (Arr::make($record)->keys()->diff($allowed)->val() !== []) { throw new InvalidArgumentException('Unknown workflow field.'); }
    }
}
