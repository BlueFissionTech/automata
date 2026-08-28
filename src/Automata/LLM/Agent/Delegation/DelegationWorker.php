<?php

namespace BlueFission\Automata\LLM\Agent\Delegation;

use BlueFission\Arr;
use BlueFission\Func;
use Throwable;

class DelegationWorker
{
    public function __construct(
        protected AgentProfile $profile,
        protected mixed $handler
    ) {
    }

    public function __invoke(array $context = [], array $priorResults = []): array
    {
        $request = $this->request($context);
        if (!$request) {
            return $this->normalize(DelegationResult::rejected(
                new DelegationRequest(['target_agent_id' => $this->profile->id()]),
                $this->profile->id(),
                'delegation_request_missing'
            ));
        }

        if ($request->targetAgentId() !== $this->profile->id()) {
            return $this->normalize(DelegationResult::rejected($request, $this->profile->id(), 'target_agent_mismatch'));
        }

        if ($request->isCancelled()) {
            return $this->normalize(DelegationResult::fromRequest(
                $request,
                $this->profile->id(),
                DelegationStatus::CANCELLED
            ));
        }

        if ($request->isTimedOut()) {
            return $this->normalize(DelegationResult::fromRequest(
                $request,
                $this->profile->id(),
                DelegationStatus::TIMED_OUT
            ));
        }

        foreach ($request->requiredCapabilities() as $capability) {
            if (!$this->profile->hasCapability((string)$capability) || !$request->permits((string)$capability)) {
                return $this->normalize(DelegationResult::rejected(
                    $request,
                    $this->profile->id(),
                    'capability_not_granted:' . $capability
                ));
            }
        }

        if (!Func::isCallable($this->handler)) {
            return $this->normalize(DelegationResult::fromRequest(
                $request,
                $this->profile->id(),
                DelegationStatus::FAILED,
                null,
                ['reason' => 'handler_not_callable']
            ));
        }

        try {
            $result = ($this->handler)($request, $this->peerResults($request, $priorResults));
            if (!$result instanceof DelegationResult) {
                $result = DelegationResult::fromRequest(
                    $request,
                    $this->profile->id(),
                    DelegationStatus::COMPLETED,
                    $result
                );
            }
        } catch (Throwable $exception) {
            $result = DelegationResult::fromRequest(
                $request,
                $this->profile->id(),
                DelegationStatus::FAILED,
                null,
                ['reason' => $exception->getMessage(), 'exception' => $exception::class]
            );
        }

        return $this->normalize($result);
    }

    protected function request(array $context): ?DelegationRequest
    {
        $requests = Arr::make($context['delegations'] ?? [])->toArray();
        $request = $requests[$this->profile->id()] ?? $context['delegation'] ?? null;
        if ($request instanceof DelegationRequest) {
            return $request;
        }

        return Arr::is($request) ? new DelegationRequest($request) : null;
    }

    protected function peerResults(DelegationRequest $request, array $priorResults): array
    {
        if (!$this->profile->hasCapability('peer.results.read') || !$request->allowsPeerResults()) {
            return [];
        }

        $allowedPeers = $request->peerAgentIds();

        return array_values(array_filter(
            $priorResults,
            static fn (mixed $result): bool => Arr::is($result)
                && in_array((string)($result['name'] ?? ''), $allowedPeers, true)
        ));
    }

    protected function normalize(DelegationResult $result): array
    {
        return [
            'status' => $result->status(),
            'output' => $result->output(),
            'confidence' => in_array($result->status(), [DelegationStatus::COMPLETED, DelegationStatus::PARTIAL], true) ? 1.0 : 0.0,
            'metadata' => ['delegation' => $result->toArray()],
        ];
    }
}
