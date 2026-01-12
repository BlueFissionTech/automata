<?php
namespace BlueFission\Automata\Intent;

use BlueFission\DevElation as Dev;

// Intent.php
class Intent
{
    protected $_label;
    protected $_name;
    protected $_criteria;
    protected $_relatedIntents;

    public function __construct(string $label, string $name, array $criteria = [])
    {
        $this->_label = Dev::apply('intent.init.label', $label);
        $this->_name = Dev::apply('intent.init.name', $name);
        $this->_criteria = Dev::apply('intent.init.criteria', $criteria);
        $this->_relatedIntents = [];
        Dev::do('intent.created', ['intent' => $this]);
    }

    public function getLabel(): string
    {
        return Dev::apply('intent.get_label', $this->_label);
    }

    public function getName(): string
    {
        return Dev::apply('intent.get_name', $this->_name);
    }

    public function getCriteria(): array
    {
        return Dev::apply('intent.get_criteria', $this->_criteria);
    }

    public function getRelatedIntents(): array
    {
        return Dev::apply('intent.get_related', $this->_relatedIntents);
    }

    public function addCriteria($label, $criteria)
    {
        $label = Dev::apply('intent.add_label', $label);
        $criteria = Dev::apply('intent.add_criteria', $criteria);
        $this->_criteria[$label][] = $criteria;
        Dev::do('intent.criteria_added', ['intent' => $this, 'criteria_label' => $label, 'criteria' => $criteria]);
    }
}
