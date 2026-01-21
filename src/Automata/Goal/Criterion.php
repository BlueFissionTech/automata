<?php

namespace BlueFission\Automata\Goal;

use BlueFission\DevElation as Dev;

class Criterion extends InitiativeObject
{
    public function key(): string
    {
        $type = (string)$this->field('type');
        $operator = (string)$this->field('operator');
        $value = (string)$this->field('value');

        $key = strtolower(trim($type . '_' . $operator . '_' . $value));
        return Dev::apply('goal.criterion.key', $key);
    }

    public function priority(): float
    {
        $priority = $this->field('priority');
        return is_numeric($priority) ? (float)$priority : 0.0;
    }

    public function satisfied(bool $value = null): bool
    {
        if ($value !== null) {
            $this->field('is_satisfied', $value);
            Dev::do('goal.criterion.satisfied', ['criterion' => $this, 'value' => $value]);
        }

        return (bool)$this->field('is_satisfied');
    }
}
