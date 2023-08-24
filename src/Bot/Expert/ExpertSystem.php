<?php
namespace BlueFission\Automata\ExpertSystem;

use BlueFission\Behavioral\Configurable;

class Expert implements IConfigurable
{
    use Configurable {
        Configurable::__construct as private __configConstruct;
    }

    protected array $facts = [];
    protected array $rules = [];
    protected ?StrategyInterface $strategy = null;

    public function __construct(array $rules = [], array $facts = [])
    {
        $this->__configConstruct();
        $this->_rules = $rules;
        $this->_facts = $facts;
    }

    public function addFact(FactInterface $fact)
    {
        $this->facts[$fact->getName()] = $fact;
    }

    public function addRule(RuleInterface $rule)
    {
        $this->rules[$rule->getName()] = $rule;
    }

    public function setStrategy(StrategyInterface $strategy)
    {
        $this->strategy = $strategy;
    }

    public function reason(): bool
    {
        if (!$this->strategy) {
            throw new \RuntimeException("No strategy has been set.");
        }

        return $this->strategy->execute($this);
    }

    public function getFacts(): array
    {
        return $this->facts;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function infer()
    {
        $changes = false;

        do {
            $changes = false;

            foreach ($this->_rules as $rule) {
                if ($rule->isApplicable($this->_facts)) {
                    $fact = $rule->infer();
                    $this->addFact($fact);
                    $changes = true;
                }
            }
        } while ($changes);
    }

    public function query(Fact $fact)
    {
        return in_array($fact, $this->_facts);
    }
}
