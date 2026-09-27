<?php

namespace BlueFission\Automata\Strategy;

use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\Strategy\Routing\StrategyRouter;
use BlueFission\Automata\Strategy\Workflow\{StrategyWorkflow, StrategyWorkflowRun, StrategyWorkflowResult};
use Closure;
use LogicException;
use RuntimeException;

/** A declared graph is usable wherever an IStrategy prediction is accepted. */
final class CompositeStrategy implements IStrategy
{
    private Closure $authorize;
    private ?StrategyWorkflowResult $lastResult = null;

    public function __construct(private readonly StrategyWorkflow $workflow, private readonly StrategyRouter $router,
        callable $authorize, private readonly string $subjectId, private readonly int $maximumDispatches = 256,
        private readonly ?TaskTrace $trace = null)
    {
        $this->authorize = Closure::fromCallable($authorize);
    }

    public function start(mixed $input, ?string $runId = null, string $contextKey = ''): StrategyWorkflowRun
    {
        return new StrategyWorkflowRun($this->workflow, $this->router, $this->authorize, $this->subjectId,
            $input, $runId ?? 'workflow-' . bin2hex(random_bytes(16)), $this->maximumDispatches, $contextKey, $this->trace);
    }

    /** Convenience scheduler executes serially; hosts may schedule start() runs themselves. */
    public function predict($input)
    {
        $this->lastResult = null;
        $run = $this->start($input);
        while (($ready = $run->ready()) !== []) { $run->execute($ready[0]); }
        $this->lastResult = $run->result();
        if ($this->lastResult->status() !== 'completed' || !$this->lastResult->toArray()['settled']) { throw new RuntimeException('Composite strategy did not complete; inspect lastResult().'); }
        return $this->lastResult->outputs();
    }

    public function lastResult(): ?StrategyWorkflowResult { return $this->lastResult; }
    public function train(array $samples, array $labels, float $testSize = 0.2) { throw new LogicException('Train isolated node strategies; workflow declarations are not trained in place.'); }
    public function accuracy(): float { throw new LogicException('Composite accuracy requires independent evaluation.'); }
    public function saveModel(string $path): bool { throw new LogicException('Export the workflow proposal with toArray(); host model bindings are not serialized.'); }
    public function loadModel(string $path): bool { throw new LogicException('Import a workflow proposal and supply reviewed host bindings explicitly.'); }
}
