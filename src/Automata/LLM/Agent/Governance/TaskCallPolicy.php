<?php

namespace BlueFission\Automata\LLM\Agent\Governance;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Behavioral\Configurable;
use BlueFission\Behavioral\IConfigurable;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\DevElation as Dev;

class TaskCallPolicy implements IConfigurable, IDispatcher
{
    use Configurable {
        Configurable::__construct as private __configurableConstruct;
    }

    protected array $_config = [];

    /**
     * Create a configurable governance policy for external task calls.
     */
    public function __construct(array $config = [])
    {
        $this->_config = ToolDefinition::mergeConfig($this->defaults(), $config);
        $this->__configurableConstruct($this->_config);
    }

    /**
     * Decide whether a task call may run, requires review, or must be blocked.
     */
    public function assess(array $call): GovernanceDecision
    {
        $call = Dev::apply('automata.llm.agent.governance.policy.call', $call);
        $kind = (string)($call['kind'] ?? '');
        $name = (string)($call['name'] ?? '');

        if ($this->matches($kind, $name, 'blocked_kinds', 'blocked_names')) {
            return GovernanceDecision::denied('Blocked by task call policy.', [
                'kind' => $kind,
                'name' => $name,
            ]);
        }

        if (($call['approved'] ?? false) === true || (($call['metadata']['approved'] ?? false) === true)) {
            return GovernanceDecision::approved('Pre-approved by call context.');
        }

        if ($this->matches($kind, $name, 'review_kinds', 'review_names')) {
            return GovernanceDecision::pending('Human review required by task call policy.', [
                'kind' => $kind,
                'name' => $name,
            ]);
        }

        return GovernanceDecision::approved('Allowed by task call policy.');
    }

    /**
     * Return normalized policy configuration.
     */
    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.governance.policy.to_array', $this->_config);
    }

    /**
     * Return default policy fields.
     */
    protected function defaults(): array
    {
        return [
            'blocked_kinds' => [],
            'blocked_names' => [],
            'review_kinds' => [],
            'review_names' => [],
        ];
    }

    /**
     * Check kind/name lists without exposing list-shaping rules to callers.
     */
    protected function matches(string $kind, string $name, string $kindKey, string $nameKey): bool
    {
        $kinds = Arr::make($this->_config[$kindKey] ?? [])->toArray();
        $names = Arr::make($this->_config[$nameKey] ?? [])->toArray();

        return Arr::contains($kinds, $kind, true) || Arr::contains($names, $name, true);
    }
}
