<?php

namespace BlueFission\Automata\LLM\Agent\State;

use BlueFission\Arr;
use Throwable;

final class AgentModuleLifecycle
{
    public const VERSION = '1.0.0';

    private $clock;

    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    public static function contract(): array
    {
        return [
            'name' => 'automata.agent.module.lifecycle',
            'version' => self::VERSION,
            'mode' => 'synchronous',
            'supported' => [
                'explicit_host_authorization',
                'pre_invocation_cancellation',
                'exception_normalization',
                'measured_duration_limits',
                'trace_lineage',
            ],
            'unsupported' => [
                'in_flight_cancellation',
                'progressive_output',
                'resume',
                'hard_preemption',
                'exactly_once_effects',
            ],
            'effect_authorization_owner' => 'host',
            'idempotency_owner' => 'host',
        ];
    }

    public function run(
        IAgentModule $module,
        AgentState $state,
        AgentModuleRunRequest $request
    ): AgentModuleLifecycleResult {
        if (!$request->authorization()->allowsExecution()) {
            return $this->result($module, $request, [
                'status' => AgentModuleLifecycleResult::DENIED,
                'diagnostics' => [[
                    'code' => 'authorization_denied',
                    'authorization_status' => $request->authorization()->status(),
                ]],
            ]);
        }

        if ($request->cancellationRequested()) {
            return $this->result($module, $request, [
                'status' => AgentModuleLifecycleResult::CANCELLED,
                'execution' => $this->execution(false, null, [
                    'reason' => 'host_cancelled',
                    'requested' => true,
                    'confirmed_stopped' => true,
                    'mechanism' => 'pre_invocation',
                ]),
            ]);
        }

        $unsupported = $this->unsupportedFeatures($request->requestedFeatures());
        if ($unsupported !== []) {
            return $this->result($module, $request, [
                'status' => AgentModuleLifecycleResult::UNSUPPORTED,
                'diagnostics' => [[
                    'code' => 'lifecycle_feature_unsupported',
                    'features' => $unsupported,
                ]],
            ]);
        }

        $startedAt = $this->now();
        try {
            $upstream = $module->process($state, $request->context());
        } catch (Throwable $exception) {
            $duration = $this->duration($startedAt, $this->now());

            return $this->result($module, $request, [
                'status' => AgentModuleLifecycleResult::FAILED,
                'execution' => $this->execution(true, $duration),
                'diagnostics' => [[
                    'code' => 'module_exception',
                    'exception_type' => $exception::class,
                ]],
            ]);
        }

        $duration = $this->duration($startedAt, $this->now());
        $data = $upstream->toArray();
        $upstreamStatus = (string)($data['status'] ?? AgentModuleLifecycleResult::COMPLETED);
        $data['upstream_status'] = $upstreamStatus;
        $data['execution'] = $this->execution(true, $duration);

        $limit = $request->limits()['max_duration_ms'] ?? null;
        if (is_numeric($limit) && (int)$limit >= 0 && $duration > (int)$limit) {
            $data['status'] = AgentModuleLifecycleResult::RESOURCE_LIMITED;
            $data['execution'] = $this->execution(true, $duration, [
                'reason' => 'duration_limit',
                'requested' => false,
                'confirmed_stopped' => true,
                'mechanism' => 'observed_after_completion',
            ]);
            $data['diagnostics'] = [[
                'code' => 'duration_limit_exceeded',
                'limit_ms' => (int)$limit,
                'observed_ms' => $duration,
                'enforcement' => 'observed_after_completion',
            ]];
        }

        return $this->result($module, $request, $data);
    }

    private function result(
        IAgentModule $module,
        AgentModuleRunRequest $request,
        array $data
    ): AgentModuleLifecycleResult {
        $data['module'] = $data['module'] ?? $module->name();
        $data['contract'] = self::contract();
        $data['lineage'] = $request->lineage();
        $data['authorization'] = $request->authorization()->toArray();
        $data['diagnostics'] = Arr::make($data['diagnostics'] ?? [])->toArray();
        $data['execution'] = Arr::make($data['execution'] ?? $this->execution(false))->toArray();

        return new AgentModuleLifecycleResult($data);
    }

    private function execution(bool $invoked, ?int $duration = null, array $termination = []): array
    {
        return [
            'invoked' => $invoked,
            'state' => $invoked ? 'completed' : 'not_started',
            'duration_ms' => $duration,
            'termination' => [
                'reason' => $termination['reason'] ?? null,
                'requested' => $termination['requested'] ?? null,
                'confirmed_stopped' => $termination['confirmed_stopped'] ?? null,
                'mechanism' => $termination['mechanism'] ?? null,
            ],
            'evidence' => [
                'in_flight' => null,
                'uncertain' => null,
            ],
            'effects' => [
                'authorization_owner' => 'host',
                'idempotency_owner' => 'host',
                'attributed_after_terminal' => null,
            ],
        ];
    }

    private function unsupportedFeatures(array $requested): array
    {
        $unsupported = self::contract()['unsupported'];

        return Arr::make($requested)
            ->filter(static fn (string $feature): bool => Arr::has($unsupported, $feature, true))
            ->toArray();
    }

    private function now(): float
    {
        return (float)($this->clock)();
    }

    private function duration(float $startedAt, float $endedAt): int
    {
        return max(0, (int)round(($endedAt - $startedAt) * 1000));
    }
}
