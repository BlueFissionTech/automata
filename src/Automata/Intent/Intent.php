<?php
namespace BlueFission\Automata\Intent;

// Intent.php
class Intent
{
    protected $_label;
    protected $_name;
    protected $_criteria;
    protected $_relatedIntents;

    public function __construct(string $label, string $name, array $criteria = [])
    {
        $this->_label = $label;
        $this->_name = $name;
        $this->_criteria = $criteria;
        $this->_relatedIntents = [];
    }

    public function getLabel(): string
    {
        return $this->_label;
    }

    public function getName(): string
    {
        return $this->_name;
    }

    public function getCriteria(): array
    {
        return $this->_criteria;
    }

    public function getRelatedIntents(): array
    {
        return $this->_relatedIntents;
    }

    public function addCriteria($label, $criteria)
    {
        $this->_criteria[$label][] = $criteria;
    }
}
