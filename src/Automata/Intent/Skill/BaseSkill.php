<?php
// BaseSkill.php
namespace BlueFission\Automata\Intent\Skill;

use BlueFission\Automata\Context;

abstract class BaseSkill implements ISkill {
    protected $_name;

    public function __construct(string $name) {
        $this->_name = $name;
    }

    public function name(): string {
        return $this->_name;
    }

    abstract public function execute(Context $context);

    abstract public function response(): string;
}
