<?php

namespace BlueFission\Automata\LLM\Agent;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\Automata\Memory\IWorkingMemory;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\IDispatcher;

class AgentSession implements IDispatcher
{
    use Dispatches {
        Dispatches::__construct as private __dispatchesConstruct;
    }

    protected string $sessionId;

    protected array $context = [];

    protected array $permissions = [];

    protected ?IWorkingMemory $workingMemory = null;

    /**
     * Create a scope boundary for one or more agents.
     */
    public function __construct(?string $sessionId = null, array $context = [], ?IWorkingMemory $workingMemory = null)
    {
        $this->__dispatchesConstruct();
        $this->sessionId = $sessionId ?: TaskTraceSpan::id('session');
        $this->context = $context;
        $this->workingMemory = $workingMemory;
        $this->trigger(Event::STARTED);
    }

    /**
     * Return the stable session identifier.
     */
    public function id(): string
    {
        return $this->sessionId;
    }

    /**
     * Read or extend shared session context.
     */
    public function context(array $extra = []): array
    {
        return ToolDefinition::mergeConfig($this->context, $extra);
    }

    /**
     * Replace session context.
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        $this->trigger(Event::CHANGE);

        return $this;
    }

    /**
     * Grant a permission inside this session scope.
     */
    public function allow(string $permission): self
    {
        if (!Arr::contains($this->permissions, $permission, true)) {
            $this->permissions[] = $permission;
            $this->trigger(Event::CHANGE);
        }

        return $this;
    }

    /**
     * Determine whether a permission is available in this session.
     */
    public function can(string $permission): bool
    {
        return Arr::contains($this->permissions, $permission, true);
    }

    /**
     * Attach Automata working memory to the session scope.
     */
    public function useWorkingMemory(?IWorkingMemory $memory): self
    {
        $this->workingMemory = $memory;
        $this->trigger(Event::CHANGE);

        return $this;
    }

    /**
     * Return the attached working memory, if any.
     */
    public function workingMemory(): ?IWorkingMemory
    {
        return $this->workingMemory;
    }

    /**
     * Store context in the attached working memory.
     */
    public function remember(string $label, Context $context, array $edges = []): self
    {
        if ($this->workingMemory) {
            $this->workingMemory->addMemory($label, $context, $edges);
            $this->trigger(Event::CHANGE);
        }

        return $this;
    }

    /**
     * Recall context from attached working memory.
     */
    public function recall(string $label): ?Context
    {
        return $this->workingMemory?->recall($label);
    }
}
