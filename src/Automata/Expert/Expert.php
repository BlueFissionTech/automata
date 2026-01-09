<?php
namespace BlueFission\Automata\Expert;

use BlueFission\Arr;
use BlueFission\Behavioral\Configurable;
use BlueFission\Behavioral\IConfigurable;
use BlueFission\Behavioral\IDispatcher;

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
        $this->_facts->set($fact->getName(), $fact);
    }

    /**
     * Add a rule to the knowledge base, keyed by its name.
     */
    public function addRule(IRule $rule): void
    {
        $this->_rules->set($rule->getName(), $rule);
    }

    /**
     * Set the reasoning approach (forward/backward, etc.).
     */
    public function setStrategy(IApproach $approach): void
    {
        $this->_approach = $approach;
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

        return $this->_approach->execute($this);
    }

    /**
     * Get all facts as a plain array.
     *
     * @return array<string,IFact>
     */
    public function getFacts(): array
    {
        return $this->_facts->val();
    }

    /**
     * Get all rules as a plain array.
     *
     * @return array<string,IRule>
     */
    public function getRules(): array
    {
        return $this->_rules->val();
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

            foreach ($this->_rules as $rule) {
                if ($rule->isApplicable($this->_facts->val())) {
                    $fact = $rule->infer();
                    // Avoid infinite loops by only adding facts
                    // that are not already present by name.
                    if (!$this->query($fact)) {
                        $this->addFact($fact);
                        $changes = true;
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
        return $this->_facts->hasKey($fact->getName());
    }
}
