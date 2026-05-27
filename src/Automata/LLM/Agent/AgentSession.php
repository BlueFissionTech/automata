<?php

namespace BlueFission\Automata\LLM\Agent;

use BlueFission\Arr;
use BlueFission\Automata\Comprehension\Holoscene;
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

    protected ?Holoscene $holoscene = null;

    /**
     * Create a scope boundary for one or more agents.
     */
    public function __construct(?string $sessionId = null, array $context = [], ?IWorkingMemory $workingMemory = null, ?Holoscene $holoscene = null)
    {
        $this->__dispatchesConstruct();
        $this->sessionId = $sessionId ?: TaskTraceSpan::id('session');
        $this->context = $context;
        $this->workingMemory = $workingMemory;
        $this->holoscene = $holoscene;
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
     * Attach a Holoscene narrative container to this session scope.
     */
    public function useHoloscene(?Holoscene $holoscene): self
    {
        $this->holoscene = $holoscene;
        $this->trigger(Event::CHANGE);

        return $this;
    }

    /**
     * Return the attached Holoscene, if any.
     */
    public function holoscene(): ?Holoscene
    {
        return $this->holoscene;
    }

    /**
     * Store a scene-like episode in the attached Holoscene.
     */
    public function addHolosceneEpisode(string $episodeId, mixed $scene): self
    {
        if ($this->holoscene) {
            $this->holoscene->push($episodeId, $scene);
            $this->trigger(Event::CHANGE);
        }

        return $this;
    }

    /**
     * Return the attached Holoscene snapshot without exposing mutable internals.
     */
    public function holosceneSnapshot(): ?array
    {
        return $this->holoscene?->snapshot();
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
