<?php

namespace BlueFission\Automata\Strategy\Workflow;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
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
        $outputCount = Arr::size($outputs);
        if ($outputCount > 128) {
            throw new InvalidArgumentException('Too many output nodes.');
        }

        $outputs = RecordSnapshot::copy($outputs);
        $nodes = $graph->nodes();
        $nodeCount = Arr::size($nodes);
        if ($nodeCount < 1 || $nodeCount > 128) {
            throw new InvalidArgumentException('Workflows require 1..128 nodes.');
        }

        $configs = [];
        $edges = [];

        // Copy metadata before validating it so the proposal cannot retain live
        // objects or be changed through the source graph after construction.
        foreach ($nodes as $node) {
            $name = $node->getName();
            if (!Str::matches($name, '/^[A-Za-z][A-Za-z0-9_.-]{0,127}$/D')) {
                throw new InvalidArgumentException('Invalid workflow node id.');
            }
            if (!isset($nodes[$name]) || $nodes[$name] !== $node || isset($configs[$name])) {
                throw new InvalidArgumentException('Graph node identity is inconsistent.');
            }

            $config = RecordSnapshot::copy($node->meta['workflow'] ?? null);
            if (!Arr::is($config)) {
                throw new InvalidArgumentException('Nodes require workflow metadata.');
            }

            self::keys($config, ['strategy_id', 'strategy_version', 'capability_id', 'capability_version', 'allowed_modes', 'limits', 'join', 'maximum_attempts', 'retry_codes']);
            foreach (['strategy_id', 'strategy_version', 'capability_id', 'capability_version'] as $field) {
                RecordSnapshot::identifier($config[$field] ?? null, $field);
            }

            $config += ['allowed_modes' => ['deterministic'], 'limits' => [], 'join' => 'all', 'maximum_attempts' => 1, 'retry_codes' => []];
            if (!Arr::is($config['allowed_modes']) || !array_is_list($config['allowed_modes']) || $config['allowed_modes'] === []) {
                throw new InvalidArgumentException('Invalid allowed strategy modes.');
            }
            foreach ($config['allowed_modes'] as $mode) {
                if (!Arr::make(['deterministic', 'learned', 'generative'])->has($mode, true)) { throw new InvalidArgumentException('Invalid allowed strategy mode.'); }
            }
            if (!Arr::make(['all', 'any'])->has($config['join'], true)
                || !Num::isInt($config['maximum_attempts'])
                || $config['maximum_attempts'] < 1
                || $config['maximum_attempts'] > 8
                || !Arr::is($config['retry_codes'])
                || !array_is_list($config['retry_codes'])) {
                throw new InvalidArgumentException('Invalid join or retry bounds.');
            }
            foreach ($config['retry_codes'] as $code) {
                RecordSnapshot::identifier($code, 'retry code');
            }
            if (!Arr::is($config['limits'])) {
                throw new InvalidArgumentException('Node limits must be a map.');
            }

            self::keys($config['limits'], ['max_cost', 'max_latency_ms', 'max_energy', 'max_invocations']);
            foreach ($config['limits'] as $key => $value) {
                RecordSnapshot::number($value, $key);
            }

            $configs[$name] = $config;

            // Edges carry only strict status and value predicates. A missing or
            // malformed path cannot silently become a broad dependency match.
            foreach ($graph->neighbors($name) as $to) {
                if (!isset($nodes[$to])) {
                    throw new InvalidArgumentException('Unknown dependency node.');
                }
                $edge = RecordSnapshot::copy($graph->edgeAttributes($name, $to));
                self::keys($edge, ['on', 'when']);
                $edge += ['on' => 'completed'];
                if (!Arr::make(['completed', 'failed'])->has($edge['on'], true)) {
                    throw new InvalidArgumentException('Invalid edge status.');
                }
                if (Arr::make($edge)->hasKey('when')) {
                    $when = $edge['when'];
                    if (!Arr::is($when)) {
                        throw new InvalidArgumentException('Invalid edge condition.');
                    }
                    self::keys($when, ['path', 'equals']);
                    if (!isset($when['path'])
                        || !Arr::is($when['path'])
                        || !array_is_list($when['path'])
                        || Arr::size($when['path']) > 16
                        || !Arr::make($when)->hasKey('equals')) {
                        throw new InvalidArgumentException('Condition requires path and equals.');
                    }
                    foreach ($when['path'] as $key) {
                        if (!Num::isInt($key) && !Str::is($key)) {
                            throw new InvalidArgumentException('Invalid condition path.');
                        }
                    }
                }
                $edges[] = ['from' => $name, 'to' => $to, ...$edge];
                if (Arr::size($edges) > 512) {
                    throw new InvalidArgumentException('Workflows allow at most 512 edges.');
                }
            }
        }
        // Kahn's walk rejects cycles while preserving the graph's declared
        // order. A cycle cannot be made safe by a per-node retry bound.
        $degrees = Arr::make($configs)->map(static fn (): int => 0)->val();
        foreach ($edges as $edge) {
            ++$degrees[$edge['to']];
        }
        $queue = Arr::make($degrees)->filter(static fn ($degree) => $degree === 0)->keys();
        $visited = 0;
        while ($queue->size() > 0) {
            $from = $queue->shift();
            ++$visited;
            foreach ($edges as $edge) {
                if ($edge['from'] === $from && --$degrees[$edge['to']] === 0) {
                    $queue[] = $edge['to'];
                }
            }
        }
        if ($visited !== Arr::size($configs)) {
            throw new InvalidArgumentException('Workflow cycles are not permitted; use bounded node retries.');
        }

        // Keep SORT_REGULAR at this validation boundary: Arr::unique() uses
        // SORT_STRING, which can coerce malformed values before they are rejected.
        if (!array_is_list($outputs)
            || $outputs === []
            || Arr::size(array_unique($outputs, SORT_REGULAR)) !== $outputCount) {
            throw new InvalidArgumentException('Output nodes must be a unique nonempty list.');
        }
        foreach ($outputs as $output) {
            if (!Str::is($output) || !isset($configs[$output])) {
                throw new InvalidArgumentException('Unknown output node.');
            }
        }

        $minimumOutputs ??= $outputCount;
        if ($minimumOutputs < 1 || $minimumOutputs > $outputCount) {
            throw new InvalidArgumentException('Invalid output completion threshold.');
        }

        $this->record = RecordSnapshot::copy([
            'schema_version' => 1,
            'id' => $id,
            'version' => $version,
            'nodes' => Arr::make($configs)->map(static fn ($config, $name) => ['id' => $name, 'config' => $config])->values()->val(),
            'edges' => $edges,
            'outputs' => $outputs,
            'minimum_outputs' => $minimumOutputs,
        ]);
    }

    public function toArray(): array
    {
        return $this->record;
    }

    /** Imports proposals only, never router bindings, authorization or live execution state. */
    public static function fromArray(array $record): self
    {
        self::keys($record, ['schema_version', 'id', 'version', 'nodes', 'edges', 'outputs', 'minimum_outputs']);
        if (($record['schema_version'] ?? null) !== 1
            || !Arr::is($record['nodes'] ?? null)
            || !array_is_list($record['nodes'])
            || Arr::size($record['nodes']) > 128
            || !Arr::is($record['edges'] ?? null)
            || !array_is_list($record['edges'])
            || Arr::size($record['edges']) > 512
            || !Arr::is($record['outputs'] ?? null)
            || !Num::isInt($record['minimum_outputs'] ?? null)) {
            throw new InvalidArgumentException('Invalid workflow record.');
        }

        // Rebuild rather than trust imported topology or authority: the
        // constructor applies the same identity and bounds checks as new plans.
        $record = RecordSnapshot::copy($record);
        RecordSnapshot::identifier($record['id'] ?? null, 'workflow id');
        RecordSnapshot::identifier($record['version'] ?? null, 'workflow version');
        $graph = new Graph();
        $seen = [];
        foreach ($record['nodes'] as $node) {
            if (!Arr::is($node)) {
                throw new InvalidArgumentException('Invalid node record.');
            }
            self::keys($node, ['id', 'config']);
            $id = RecordSnapshot::identifier($node['id'] ?? null, 'node id');
            if (isset($seen[$id]) || !Arr::is($node['config'] ?? null)) {
                throw new InvalidArgumentException('Duplicate or malformed node.');
            }
            $seen[$id] = true;
            $graph->addNode(new Node($id, null, [], ['workflow' => $node['config']]));
        }
        foreach ($record['edges'] as $edge) {
            if (!Arr::is($edge)) {
                throw new InvalidArgumentException('Invalid edge record.');
            }
            self::keys($edge, ['from', 'to', 'on', 'when']);
            $from = RecordSnapshot::identifier($edge['from'] ?? null, 'edge source');
            $to = RecordSnapshot::identifier($edge['to'] ?? null, 'edge target');
            if (!isset($seen[$from], $seen[$to]) || $graph->edgeAttributes($from, $to) !== null) {
                throw new InvalidArgumentException('Unknown or duplicate edge.');
            }
            unset($edge['from'], $edge['to']);
            $graph->connect($from, $to, $edge);
        }
        $plan = new self($record['id'], $record['version'], $graph, $record['outputs'], $record['minimum_outputs']);
        if (RecordSnapshot::fingerprint($plan->toArray()) !== RecordSnapshot::fingerprint($record)) {
            throw new InvalidArgumentException('Workflow import must preserve the canonical exported plan.');
        }
        return $plan;
    }

    private static function keys(array $record, array $allowed): void
    {
        if (Arr::make($record)->keys()->diff($allowed)->val() !== []) {
            throw new InvalidArgumentException('Unknown workflow field.');
        }
    }
}
