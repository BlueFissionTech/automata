<?php

namespace BlueFission\Automata\LLM\Agent\State;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;

class AgentState
{
    public const GOALS = 'goals';
    public const OBSERVATIONS = 'observations';
    public const DECISIONS = 'decisions';
    public const EXPECTATIONS = 'expectations';
    public const OUTPUTS = 'outputs';
    public const SOCIAL = 'social';
    public const RULES = 'rules';
    public const REFLECTIONS = 'reflections';

    protected array $channels;

    public function __construct(array $channels = [])
    {
        $this->channels = array_replace_recursive($this->defaults(), $channels);
    }

    public function write(string $channel, string $key, mixed $value): self
    {
        $this->ensureChannel($channel);
        $this->channels[$channel][$key] = $value;
        Dev::do('automata.llm.agent.state.write', [
            'channel' => $channel,
            'key' => $key,
            'value' => $value,
        ]);

        return $this;
    }

    public function append(string $channel, mixed $value, ?string $key = null): self
    {
        $this->ensureChannel($channel);
        if ($key === null) {
            $this->channels[$channel][] = $value;
        } else {
            if (!isset($this->channels[$channel][$key]) || !Arr::is($this->channels[$channel][$key])) {
                $this->channels[$channel][$key] = [];
            }
            $this->channels[$channel][$key][] = $value;
        }

        return $this;
    }

    public function read(string $channel, ?string $key = null, mixed $default = null): mixed
    {
        $this->ensureChannel($channel);
        if ($key === null) {
            return $this->channels[$channel];
        }

        return $this->channels[$channel][$key] ?? $default;
    }

    public function channel(string $channel): array
    {
        return $this->read($channel);
    }

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
            $selected[$channel] = array_slice($values, 0, $limitPerChannel, true);
        }

        return $selected;
    }

    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.state.to_array', $this->channels);
    }

    protected function ensureChannel(string $channel): void
    {
        if (!isset($this->channels[$channel]) || !Arr::is($this->channels[$channel])) {
            $this->channels[$channel] = [];
        }
    }

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
}
