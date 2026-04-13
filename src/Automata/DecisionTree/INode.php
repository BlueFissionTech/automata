<?php
namespace BlueFission\Automata\DecisionTree;

use BlueFission\Func;

interface INode {
    public function getValue(): array;
    public function getChildren(): array;
    public function addChild(INode $child): void;
    public function evaluate(array $state = [], Func|callable|null $assessor = null): int|float;
}
