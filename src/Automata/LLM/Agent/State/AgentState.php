<?php

namespace BlueFission\Automata\LLM\Agent\State;

use BlueFission\Arr;
use BlueFission\Automata\Goal\GoalManager;
use BlueFission\Automata\Goal\Initiative;
use BlueFission\Behavioral\Behaviors\Action;
use BlueFission\Behavioral\Behaviors\State as BehaviorState;
use BlueFission\Behavioral\StateMachine;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;

class AgentState extends Obj
{
    use StateMachine;

    public const GOALS = 'goals';
    public const OBSERVATIONS = 'observations';
    public const DECISIONS = 'decisions';
    public const EXPECTATIONS = 'expectations';
    public const OUTPUTS = 'outputs';
    public const SOCIAL = 'social';
    public const RULES = 'rules';
    public const REFLECTIONS = 'reflections';

    public const STATE_OBSERVING = 'IsObserving';
    public const STATE_REASONING = 'IsReasoning';
    public const STATE_ACTING = 'IsActing';
    public const STATE_SPEAKING = 'IsSpeaking';
    public const STATE_REFLECTING = 'IsReflecting';
    public const STATE_SOCIALIZING = 'IsSocializing';

    public const ACTION_DECIDE = 'DoDecide';
    public const ACTION_USE_TOOL = 'DoUseTool';
    public const ACTION_SPEAK = 'DoSpeak';
    public const ACTION_REMEMBER = 'DoRemember';

    protected array $channels;
    protected GoalManager $goalManager;

    public function __construct(array $channels = [], ?GoalManager $goalManager = null)
    {
        parent::__construct();

        $this->channels = $this->mergeChannels($this->defaults(), $channels);
        $this->goalManager = $goalManager ?? new GoalManager();
        $this->registerAgentBehaviors();
        $this->perform(BehaviorState::IDLE);
    }

    /**
     * Write a named value into one isolated state channel.
     */
    public function write(string $channel, string $key, mixed $value): self
    {
        $this->ensureChannel($channel);
        $this->channels[$channel][$key] = $value;

        if ($channel === self::GOALS && $value instanceof Initiative) {
            $this->goalManager->addGoal($value);
        }

        Dev::do('automata.llm.agent.state.write', [
            'channel' => $channel,
            'key' => $key,
            'value' => $value,
        ]);

        return $this;
    }

    /**
     * Append an unnamed or grouped value into a channel.
     */
    public function append(string $channel, mixed $value, ?string $key = null): self
    {
        $this->ensureChannel($channel);
        if ($key === null) {
            $this->channels[$channel][] = $value;
            return $this;
        }

        if (!Arr::hasKey($this->channels[$channel], $key) || !Arr::is($this->channels[$channel][$key])) {
            $this->channels[$channel][$key] = [];
        }

        $this->channels[$channel][$key][] = $value;

        return $this;
    }

    /**
     * Read one channel or one named value from a channel.
     */
    public function read(string $channel, ?string $key = null, mixed $default = null): mixed
    {
        $this->ensureChannel($channel);
        if ($key === null) {
            return $this->channels[$channel];
        }

        return Arr::hasKey($this->channels[$channel], $key)
            ? $this->channels[$channel][$key]
            : $default;
    }

    /**
     * Return all values written to one channel.
     */
    public function channel(string $channel): array
    {
        return $this->read($channel);
    }

    /**
     * Attach the Automata goal manager used by cognitive decisions.
     */
    public function useGoalManager(GoalManager $goalManager): self
    {
        $this->goalManager = $goalManager;

        return $this;
    }

    /**
     * Return the Automata goal manager for initiative and expectation evaluation.
     */
    public function goals(): GoalManager
    {
        return $this->goalManager;
    }

    /**
     * Register an Automata initiative as an active goal for this agent state.
     */
    public function addGoal(Initiative $initiative): self
    {
        $this->goalManager->addGoal($initiative);
        $key = (string)($initiative->field('initiative_id') ?? $initiative->field('name') ?? $this->goalManager->goalKey($initiative));
        $this->write(self::GOALS, $key, $initiative);

        return $this;
    }

    /**
     * Activate a behavioral state without replacing other active states.
     */
    public function enter(string $stateName): self
    {
        $this->registerState($stateName);
        $this->perform($stateName);

        return $this;
    }

    /**
     * Deactivate one behavioral state.
     */
    public function leave(string $stateName): self
    {
        $this->halt($stateName);

        return $this;
    }

    /**
     * Limit which behaviors may run while the named state is active.
     */
    public function allowInState(string $stateName, string|array $behaviors): self
    {
        $this->registerState($stateName);
        $this->registerBehaviors($behaviors);
        $this->allows($stateName, $behaviors);

        return $this;
    }

    /**
     * Deny selected behaviors while the named state is active.
     */
    public function denyInState(string $stateName, string|array $behaviors): self
    {
        $this->registerState($stateName);
        $this->registerBehaviors($behaviors);
        $this->denies($stateName, $behaviors);

        return $this;
    }

    /**
     * Check a behavior against the DevElation state machine.
     */
    public function canPerform(string $behavior): bool
    {
        $this->registerBehavior($behavior);

        return $this->can($behavior);
    }

    /**
     * Return currently active behavioral states.
     */
    public function activeStates(): array
    {
        return Arr::make($this->_state->contents())->toArray();
    }

    /**
     * Return the state channels most relevant to high-level decision making.
     */
    public function relevant(array $priorities = [], int $limitPerChannel = 5): array
    {
        $priorities = $priorities ?: [
            self::RULES,
            self::GOALS,
            self::OBSERVATIONS,
            self::SOCIAL,
            self::EXPECTATIONS,
        ];

        $selected = [];
        foreach ($priorities as $channel) {
            $values = $this->channel((string)$channel);
            $selected[$channel] = $this->limitChannel($values, $limitPerChannel);
        }

        return $selected;
    }

    /**
     * Export channel data with active behavioral states for diagnostics.
     */
    public function snapshot(): array
    {
        return [
            'channels' => $this->toArray(),
            'states' => $this->activeStates(),
            'goals' => $this->goalManager->toArray(),
        ];
    }

    /**
     * Export only state channel data for backward compatibility.
     */
    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.state.to_array', $this->channels);
    }

    /**
     * Ensure a channel exists before it is read or written.
     */
    protected function ensureChannel(string $channel): void
    {
        if (!Arr::hasKey($this->channels, $channel) || !Arr::is($this->channels[$channel])) {
            $this->channels[$channel] = [];
        }
    }

    /**
     * Register the domain-specific states and actions used by PIANO modules.
     */
    protected function registerAgentBehaviors(): void
    {
        foreach ([
            self::STATE_OBSERVING,
            self::STATE_REASONING,
            self::STATE_ACTING,
            self::STATE_SPEAKING,
            self::STATE_REFLECTING,
            self::STATE_SOCIALIZING,
        ] as $state) {
            $this->registerState($state);
        }

        $this->registerBehaviors([
            self::ACTION_DECIDE,
            self::ACTION_USE_TOOL,
            self::ACTION_SPEAK,
            self::ACTION_REMEMBER,
            Action::PROCESS,
            Action::RUN,
            Action::SEND,
            Action::TRANSFORM,
        ]);
    }

    /**
     * Register a state behavior when adopters introduce their own state names.
     */
    protected function registerState(string $stateName): void
    {
        $this->behavior(new BehaviorState($stateName));
    }

    /**
     * Register one or more action behaviors.
     */
    protected function registerBehaviors(string|array $behaviors): void
    {
        foreach (Arr::make($behaviors)->toArray() as $behavior) {
            $this->registerBehavior((string)$behavior);
        }
    }

    /**
     * Register one action behavior.
     */
    protected function registerBehavior(string $behavior): void
    {
        $this->behavior(new Action($behavior));
    }

    /**
     * Return default isolated state channels.
     */
    protected function defaults(): array
    {
        return [
            self::GOALS => [],
            self::OBSERVATIONS => [],
            self::DECISIONS => [],
            self::EXPECTATIONS => [],
            self::OUTPUTS => [],
            self::SOCIAL => [],
            self::RULES => [],
            self::REFLECTIONS => [],
        ];
    }

    /**
     * Merge channel arrays without creating a new array helper dependency.
     */
    protected function mergeChannels(array $base, array $overrides): array
    {
        foreach ($overrides as $channel => $values) {
            if (Arr::hasKey($base, $channel) && Arr::is($base[$channel]) && Arr::is($values)) {
                $base[$channel] = $this->mergeChannels($base[$channel], $values);
                continue;
            }

            $base[$channel] = $values;
        }

        return $base;
    }

    /**
     * Limit a channel while preserving associative keys.
     */
    protected function limitChannel(array $values, int $limit): array
    {
        $limited = [];
        $count = 0;
        foreach ($values as $key => $value) {
            if ($count >= $limit) {
                break;
            }

            $limited[$key] = $value;
            $count++;
        }

        return $limited;
    }
}
