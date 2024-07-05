<?php
namespace BlueFission\Automata\Expert;

use BlueFission\Behavioral\Configurable;

class Expert implements IConfigurable
{
    use Configurable {
        Configurable::__construct as private __configConstruct;
    }

    protected array $_facts;
    protected array $_rules;
    protected ?IApproach $_approach = null;

    public function __construct(array $rules = [], array $facts = [])
    {
        $this->__configConstruct();
        $this->_rules = new Arr($rules);
        $this->_facts = new Arr($facts);
    }

    public function addFact(IFact $fact)
    {
        $this->_facts->set($fact->getName(), $fact);
    }

    public function addRule(IRule $rule)
    {
        $this->_rules->set($rule->getName(), $rule);
    }

    public function setStrategy(IApproach $approach)
    {
        $this->_approach = $approach;
    }

    public function reason(): bool
    {
        if (!$this->_approach) {
            throw new \RuntimeException("No approach has been set.");
        }

        return $this->_approach->execute($this);
    }

    public function getFacts(): array
    {
        return $this->_facts->val();
    }

    public function getRules(): array
    {
        return $this->_rules->val();
    }

    public function infer()
    {
        $changes = false;

        do {
            $changes = false;

            foreach ($this->_rules as $rule) {
                if ($rule->isApplicable($this->_facts->val())) {
                    $fact = $rule->infer();
                    $this->addFact($fact);
                    $changes = true;
                }
            }
        } while ($changes);
    }

    public function query(Fact $fact)
    {
        return $this->_facts->has($fact);
    }
}
