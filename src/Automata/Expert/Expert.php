<?php
namespace BlueFission\Automata\Expert;

use BlueFission\Arr;
use BlueFission\Behavioral\Configurable;
use BlueFission\Behavioral\IConfigurable;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\DevElation as Dev;

/**
 * Expert
 *
 * Generic expert system shell that stores facts and rules
 * using Develation's Arr value object and delegates
 * reasoning to pluggable approaches/reasoners.
 */
class Expert implements IConfigurable, IDispatcher
{
    use Configurable {
        Configurable::__construct as private __configConstruct;
    }

    /** @var array<string,mixed> */
    protected array $_config = [];

    /** @var Arr<string,IFact> */
    protected Arr $_facts;

    /** @var Arr<string,IRule> */
    protected Arr $_rules;

    protected ?IApproach $_approach = null;

    public function __construct(array $rules = [], array $facts = [])
    {
        $this->__configConstruct();
        $this->_rules = new Arr($rules);
        $this->_facts = new Arr($facts);
    }

    /**
     * Add a fact to the knowledge base, keyed by its name.
     */
    public function addFact(IFact $fact): void
    {
        $fact = Dev::apply('expert.add_fact', $fact);
        $this->_facts->set($fact->getName(), $fact);
        Dev::do('expert.fact_added', ['fact' => $fact]);
    }

    /**
     * Add a rule to the knowledge base, keyed by its name.
     */
    public function addRule(IRule $rule): void
    {
        $rule = Dev::apply('expert.add_rule', $rule);
        $this->_rules->set($rule->getName(), $rule);
        Dev::do('expert.rule_added', ['rule' => $rule]);
    }

    /**
     * Set the reasoning approach (forward/backward, etc.).
     */
    public function setStrategy(IApproach $approach): void
    {
        $approach = Dev::apply('expert.set_strategy', $approach);
        $this->_approach = $approach;
        Dev::do('expert.strategy_set', ['approach' => $approach]);
    }

    /**
     * Execute the configured approach over the current
     * facts and rules.
     */
    public function reason(): bool
    {
        if (!$this->_approach) {
            throw new \RuntimeException("No approach has been set.");
        }

        Dev::do('expert.reason_start', [
            'approach' => $this->_approach,
            'facts' => $this->getFacts(),
            'rules' => $this->getRules(),
        ]);

        $result = $this->_approach->execute($this);

        $result = Dev::apply('expert.reason_complete', $result);
        Dev::do('expert.reason_result', ['result' => $result]);

        return $result;
    }

    /**
     * Get all facts as a plain array.
     *
     * @return array<string,IFact>
     */
    public function getFacts(): array
    {
        $facts = $this->_facts->val();
        return Dev::apply('expert.get_facts', $facts);
    }

    /**
     * Get all rules as a plain array.
     *
     * @return array<string,IRule>
     */
    public function getRules(): array
    {
        $rules = $this->_rules->val();
        return Dev::apply('expert.get_rules', $rules);
    }

    /**
     * Simple forward-chaining inference loop that repeatedly
     * applies applicable rules and adds their inferred facts
     * until no more changes occur.
     */
    public function infer(): void
    {
        $changes = false;

        do {
            $changes = false;

            $rules = Dev::apply('expert.infer_rules', $this->_rules->val());
            $facts = Dev::apply('expert.infer_facts', $this->_facts->val());

            foreach ($rules as $rule) {
                if ($rule->isApplicable($facts)) {
                    $fact = $rule->infer();
                    // Avoid infinite loops by only adding facts
                    // that are not already present by name.
                    if (!$this->query($fact)) {
                        $this->addFact($fact);
                        $changes = true;
                        Dev::do('expert.inferred_fact', ['inferred_fact' => $fact, 'rule' => $rule]);
                    }
                }
            }
        } while ($changes);
    }

    /**
     * Check if a fact with the same name exists in the
     * knowledge base.
     */
    public function query(Fact $fact): bool
    {
        $fact = Dev::apply('expert.query_fact', $fact);
        $result = $this->_facts->hasKey($fact->getName());
        Dev::do('expert.query_result', ['query' => $fact, 'exists' => $result]);
        return $result;
    }
}
