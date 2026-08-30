<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use InvalidArgumentException;
use Throwable;

class StrategyRouter
{
    public const CODE_COMPLETED = 'completed';
    public const CODE_INVALID_REQUEST = 'invalid_request';
    public const CODE_AUTHORIZATION_DENIED = 'authorization_denied';
    public const CODE_AUTHORIZATION_MISMATCH = 'authorization_mismatch';
    public const CODE_UNKNOWN_STRATEGY = 'unknown_strategy';
    public const CODE_CAPABILITY_MISMATCH = 'capability_mismatch';
    public const CODE_STRATEGY_UNAVAILABLE = 'strategy_unavailable';
    public const CODE_MODE_NOT_ALLOWED = 'mode_not_allowed';
    public const CODE_SIDE_EFFECTS_NOT_ALLOWED = 'side_effects_not_allowed';
    public const CODE_ESTIMATED_BUDGET_EXCEEDED = 'estimated_budget_exceeded';
    public const CODE_ACTUAL_BUDGET_EXCEEDED = 'actual_budget_exceeded';
    public const CODE_ELIGIBILITY_EXCEPTION = 'eligibility_exception';
    public const CODE_ESTIMATE_EXCEPTION = 'estimate_exception';
    public const CODE_EXECUTION_EXCEPTION = 'execution_exception';
    public const CODE_EXHAUSTED = 'strategies_exhausted';

    protected array $adapters = [];
    protected ?IStrategyRouteAdvisor $advisor;

    public function __construct(array $adapters = [], ?IStrategyRouteAdvisor $advisor = null)
    {
        $this->advisor = $advisor;

        foreach ($adapters as $adapter) {
            if (!$adapter instanceof IStrategyRouteAdapter) {
                throw new InvalidArgumentException('Strategy adapters must implement IStrategyRouteAdapter.');
            }
            $this->register($adapter);
        }
    }

    public function register(IStrategyRouteAdapter $adapter): self
    {
        $definition = $adapter->definition();
        if (
            $definition->id() === ''
            || $definition->version() === ''
            || $definition->capabilityId() === ''
            || $definition->capabilityVersion() === ''
        ) {
            throw new InvalidArgumentException('Strategy id, version, capability id, and capability version are required.');
        }

        if (!Arr::has([
            StrategyDefinition::MODE_DETERMINISTIC,
            StrategyDefinition::MODE_LEARNED,
            StrategyDefinition::MODE_GENERATIVE,
        ], $definition->mode(), true)) {
            throw new InvalidArgumentException('Strategy mode is not supported.');
        }

        $this->adapters[$this->key($definition->id(), $definition->version())] = $adapter;

        return $this;
    }

    public function advisor(?IStrategyRouteAdvisor $advisor): self
    {
        $this->advisor = $advisor;

        return $this;
    }

    public function route(
        StrategyRouteRequest $request,
        AutonomyDecision $authorization,
        ?TaskTrace $trace = null
    ): StrategyRouteResult {
        $advice = $this->declaredAdvice($request);
        $span = $trace?->startSpan(TaskTraceSpan::KIND_STRATEGY, 'strategy.route', [
            'request_id' => $request->id(),
            'capability_id' => $request->capabilityId(),
            'capability_version' => $request->capabilityVersion(),
        ]);

        if (
            $request->id() === ''
            || $request->subjectId() === ''
            || $request->capabilityId() === ''
            || $request->capabilityVersion() === ''
            || $request->candidates() === []
            || !Arr::has([
                StrategyRouteRequest::SELECTION_DECLARED,
                StrategyRouteRequest::SELECTION_ADAPTIVE,
            ], $request->selectionPolicy(), true)
        ) {
            return $this->finish($this->result(
                $request,
                $authorization,
                StrategyRouteResult::STATUS_DENIED,
                self::CODE_INVALID_REQUEST,
                [],
                new StrategyUsage(),
                null,
                null,
                [],
                $advice
            ), $request, $trace, $span);
        }

        if (!$authorization->allowed()) {
            return $this->finish($this->result(
                $request,
                $authorization,
                StrategyRouteResult::STATUS_DENIED,
                self::CODE_AUTHORIZATION_DENIED,
                [],
                new StrategyUsage(),
                null,
                null,
                [],
                $advice
            ), $request, $trace, $span);
        }

        if (
            $authorization->subjectId() !== $request->subjectId()
            || $authorization->capabilityId() !== $request->capabilityId()
            || $authorization->capabilityVersion() !== $request->capabilityVersion()
        ) {
            return $this->finish($this->result(
                $request,
                $authorization,
                StrategyRouteResult::STATUS_DENIED,
                self::CODE_AUTHORIZATION_MISMATCH,
                [],
                new StrategyUsage(),
                null,
                null,
                [],
                $advice
            ), $request, $trace, $span);
        }

        $attempts = [];
        $escalations = [];
        $usage = new StrategyUsage();
        $limits = $this->effectiveLimits($request->limits(), $authorization->limits());
        [$candidates, $advice] = $this->orderedCandidates($request);

        foreach ($candidates as $index => $candidate) {
            $candidate = Arr::make($candidate)->toArray();
            $id = (string)($candidate['id'] ?? '');
            $version = (string)($candidate['version'] ?? '');
            $adapter = $this->adapters[$this->key($id, $version)] ?? null;

            if (!$adapter) {
                $attempts[] = $this->attempt($id, $version, '', self::CODE_UNKNOWN_STRATEGY);
                continue;
            }

            $definition = $adapter->definition();
            if (
                $definition->capabilityId() !== $request->capabilityId()
                || $definition->capabilityVersion() !== $request->capabilityVersion()
            ) {
                $attempts[] = $this->attempt($id, $version, $definition->mode(), self::CODE_CAPABILITY_MISMATCH);
                continue;
            }

            if (!$definition->available()) {
                $attempts[] = $this->attempt($id, $version, $definition->mode(), self::CODE_STRATEGY_UNAVAILABLE);
                continue;
            }

            if (!$request->allowsMode($definition->mode())) {
                $attempts[] = $this->attempt($id, $version, $definition->mode(), self::CODE_MODE_NOT_ALLOWED);
                continue;
            }

            if (!$definition->sideEffectFree()) {
                $attempts[] = $this->attempt($id, $version, $definition->mode(), self::CODE_SIDE_EFFECTS_NOT_ALLOWED);
                continue;
            }

            try {
                $eligibility = $adapter->eligibility($request);
            } catch (Throwable $exception) {
                $attempts[] = $this->attempt($id, $version, $definition->mode(), self::CODE_ELIGIBILITY_EXCEPTION, [
                    'diagnostics' => [['code' => self::CODE_ELIGIBILITY_EXCEPTION, 'message' => $exception->getMessage()]],
                ]);
                continue;
            }

            if (!$eligibility->eligible()) {
                $attempts[] = $this->attempt($id, $version, $definition->mode(), $eligibility->code(), [
                    'eligible' => false,
                    'reasons' => $eligibility->reasons(),
                    'evidence' => $eligibility->toArray()['evidence'],
                ]);
                continue;
            }

            try {
                $estimate = $adapter->estimate($request)->withMinimumInvocations();
            } catch (Throwable $exception) {
                $attempts[] = $this->attempt($id, $version, $definition->mode(), self::CODE_ESTIMATE_EXCEPTION, [
                    'eligible' => true,
                    'diagnostics' => [['code' => self::CODE_ESTIMATE_EXCEPTION, 'message' => $exception->getMessage()]],
                ]);
                continue;
            }

            if (!$usage->plus($estimate)->within($limits)) {
                $attempts[] = $this->attempt($id, $version, $definition->mode(), self::CODE_ESTIMATED_BUDGET_EXCEEDED, [
                    'eligible' => true,
                    'estimate' => $estimate->toArray(),
                ]);
                continue;
            }

            try {
                $adapterResult = $adapter->execute($request);
            } catch (Throwable $exception) {
                $attempts[] = $this->attempt($id, $version, $definition->mode(), self::CODE_EXECUTION_EXCEPTION, [
                    'status' => 'failed',
                    'eligible' => true,
                    'executed' => true,
                    'estimate' => $estimate->toArray(),
                    'diagnostics' => [['code' => self::CODE_EXECUTION_EXCEPTION, 'message' => $exception->getMessage()]],
                ]);

                return $this->finish($this->result(
                    $request,
                    $authorization,
                    StrategyRouteResult::STATUS_FAILED,
                    self::CODE_EXECUTION_EXCEPTION,
                    $attempts,
                    $usage,
                    null,
                    null,
                    $escalations,
                    $advice
                ), $request, $trace, $span);
            }

            $actualUsage = $adapterResult->usage()->withMinimumInvocations();
            $usage = $usage->plus($actualUsage);
            $attempts[] = $this->attempt($id, $version, $definition->mode(), $adapterResult->code(), [
                'status' => $adapterResult->status(),
                'eligible' => true,
                'executed' => true,
                'estimate' => $estimate->toArray(),
                'actual_usage' => $actualUsage->toArray(),
                'selection_score' => $advice->score($id, $version),
                'confidence' => $adapterResult->confidence(),
                'uncertainty' => $adapterResult->uncertainty(),
                'evidence' => $adapterResult->toArray()['evidence'],
                'diagnostics' => $adapterResult->toArray()['diagnostics'],
            ]);

            if (!$usage->within($limits)) {
                return $this->finish($this->result(
                    $request,
                    $authorization,
                    StrategyRouteResult::STATUS_FAILED,
                    self::CODE_ACTUAL_BUDGET_EXCEEDED,
                    $attempts,
                    $usage,
                    $definition,
                    null,
                    [],
                    $advice
                ), $request, $trace, $span);
            }

            if ($adapterResult->succeeded()) {
                return $this->finish($this->result(
                    $request,
                    $authorization,
                    StrategyRouteResult::STATUS_COMPLETED,
                    self::CODE_COMPLETED,
                    $attempts,
                    $usage,
                    $definition,
                    $adapterResult->output(),
                    $escalations,
                    $advice
                ), $request, $trace, $span);
            }

            if ($request->canEscalate() && isset($candidates[$index + 1])) {
                $attempts[Arr::count($attempts) - 1]['escalated'] = true;
                $escalations[] = $this->escalation($id, $candidates[$index + 1], $adapterResult->code());
                continue;
            }

            return $this->finish($this->result(
                $request,
                $authorization,
                StrategyRouteResult::STATUS_FAILED,
                $adapterResult->code(),
                $attempts,
                $usage,
                $definition,
                $adapterResult->output(),
                $escalations,
                $advice
            ), $request, $trace, $span);
        }

        return $this->finish($this->result(
            $request,
            $authorization,
            StrategyRouteResult::STATUS_EXHAUSTED,
            self::CODE_EXHAUSTED,
            $attempts,
            $usage,
            null,
            null,
            $escalations,
            $advice
        ), $request, $trace, $span);
    }

    protected function orderedCandidates(StrategyRouteRequest $request): array
    {
        $advice = $this->declaredAdvice($request);
        if ($request->adaptiveSelection()) {
            if (!$this->advisor) {
                $advice = new StrategyRouteAdvice([
                    'policy' => StrategyRouteRequest::SELECTION_ADAPTIVE,
                    'diagnostics' => [[
                        'code' => 'advisor_unavailable',
                        'message' => 'Adaptive selection was requested without an injected advisor; declared order was retained.',
                    ]],
                ]);
            } else {
                try {
                    $advice = $this->advisor->advise($request, $this->candidateDefinitions($request));
                } catch (Throwable $exception) {
                    $advice = new StrategyRouteAdvice([
                        'policy' => StrategyRouteRequest::SELECTION_ADAPTIVE,
                        'diagnostics' => [[
                            'code' => 'advisor_exception',
                            'message' => $exception->getMessage(),
                        ]],
                    ]);
                }
            }
        }

        $candidates = [];
        foreach ($request->candidates() as $index => $candidate) {
            $candidateData = Arr::make($candidate)->toArray();
            $adapter = $this->adapters[$this->key(
                (string)($candidateData['id'] ?? ''),
                (string)($candidateData['version'] ?? '')
            )] ?? null;

            $candidateData['_declared_index'] = $index;
            $candidateData['_deterministic_tier'] = $request->deterministicPreferred()
                && (!$adapter || !$adapter->definition()->deterministic())
                ? 1
                : 0;
            $candidateData['_selection_score'] = $advice->score(
                (string)($candidateData['id'] ?? ''),
                (string)($candidateData['version'] ?? '')
            );
            $candidates[] = $candidateData;
        }

        usort($candidates, static function (array $left, array $right) use ($request): int {
            if ($left['_deterministic_tier'] !== $right['_deterministic_tier']) {
                return $left['_deterministic_tier'] <=> $right['_deterministic_tier'];
            }

            if ($request->adaptiveSelection()) {
                $leftScore = $left['_selection_score'];
                $rightScore = $right['_selection_score'];
                if ($leftScore !== null || $rightScore !== null) {
                    $leftScore = $leftScore ?? -INF;
                    $rightScore = $rightScore ?? -INF;
                    if ($leftScore !== $rightScore) {
                        return $leftScore < $rightScore ? 1 : -1;
                    }
                }
            }

            return $left['_declared_index'] <=> $right['_declared_index'];
        });

        foreach ($candidates as &$candidate) {
            unset(
                $candidate['_declared_index'],
                $candidate['_deterministic_tier'],
                $candidate['_selection_score']
            );
        }
        unset($candidate);

        return [$candidates, $advice];
    }

    protected function candidateDefinitions(StrategyRouteRequest $request): array
    {
        $definitions = [];
        foreach ($request->candidates() as $candidate) {
            $candidate = Arr::make($candidate)->toArray();
            $adapter = $this->adapters[$this->key(
                (string)($candidate['id'] ?? ''),
                (string)($candidate['version'] ?? '')
            )] ?? null;
            if ($adapter) {
                $definitions[] = $adapter->definition()->toArray();
            }
        }

        return $definitions;
    }

    protected function declaredAdvice(StrategyRouteRequest $request): StrategyRouteAdvice
    {
        return new StrategyRouteAdvice(['policy' => $request->selectionPolicy()]);
    }

    protected function attempt(string $id, string $version, string $mode, string $code, array $data = []): array
    {
        return (new StrategyRouteAttempt(ToolDefinition::mergeConfig([
            'strategy_id' => $id,
            'strategy_version' => $version,
            'mode' => $mode,
            'code' => $code,
        ], $data)))->toArray();
    }

    protected function escalation(string $from, mixed $candidate, string $reason): array
    {
        $next = Arr::make($candidate)->toArray();

        return [
            'from' => $from,
            'to' => (string)($next['id'] ?? ''),
            'reason' => $reason,
        ];
    }

    protected function result(
        StrategyRouteRequest $request,
        AutonomyDecision $authorization,
        string $status,
        string $code,
        array $attempts,
        StrategyUsage $usage,
        ?StrategyDefinition $selected = null,
        mixed $output = null,
        array $escalations = [],
        ?StrategyRouteAdvice $advice = null
    ): StrategyRouteResult {
        $requestData = $request->toArray();

        return new StrategyRouteResult([
            'status' => $status,
            'code' => $code,
            'request_id' => $request->id(),
            'subject_id' => $request->subjectId(),
            'capability_id' => $request->capabilityId(),
            'capability_version' => $request->capabilityVersion(),
            'selected_strategy' => $selected?->toArray(),
            'output' => $output,
            'attempts' => $attempts,
            'usage' => $usage->toArray(),
            'limits' => $this->effectiveLimits($request->limits(), $authorization->limits()),
            'selection_advice' => ($advice ?? $this->declaredAdvice($request))->toArray(),
            'escalated' => $escalations !== [],
            'escalation_history' => $escalations,
            'authorization' => $authorization->toArray(),
            'evidence' => $authorization->evidence(),
            'correlation_id' => $requestData['correlation_id'],
            'causation_id' => $requestData['causation_id'],
            'trace_id' => $requestData['trace_id'],
        ]);
    }

    protected function finish(
        StrategyRouteResult $result,
        StrategyRouteRequest $request,
        ?TaskTrace $trace,
        ?TaskTraceSpan $span
    ): StrategyRouteResult {
        if ($trace && $span) {
            $data = $result->toArray();
            $data['trace_id'] = $trace->taskId();
            $result = new StrategyRouteResult($data);
            $trace->addSpan($span->finish($result->status(), [
                'tool_spend' => $result->usage()['cost'],
                'outcome_status' => $result->status(),
                'metadata' => [
                    'code' => $result->code(),
                    'selected_strategy' => $result->selectedStrategy(),
                    'selection_advice' => $result->selectionAdvice(),
                    'usage' => $result->usage(),
                    'attempts' => $result->attempts(),
                ],
            ]));
        }

        if ($this->advisor) {
            try {
                $this->advisor->observe($request, $result);
            } catch (Throwable $exception) {
                $data = $result->toArray();
                $data['diagnostics'][] = [
                    'code' => 'advisor_observation_exception',
                    'message' => $exception->getMessage(),
                ];
                $result = new StrategyRouteResult($data);
            }
        }

        return $result;
    }

    protected function effectiveLimits(array $requested, array $authorized): array
    {
        $limits = $requested;
        foreach (['max_cost', 'max_latency_ms', 'max_energy', 'max_invocations'] as $key) {
            if (!Arr::hasKey($authorized, $key)) {
                continue;
            }

            if (!Arr::hasKey($limits, $key) || (float)$authorized[$key] < (float)$limits[$key]) {
                $limits[$key] = $authorized[$key];
            }
        }

        return $limits;
    }

    protected function key(string $id, string $version): string
    {
        return $id . '@' . $version;
    }
}
