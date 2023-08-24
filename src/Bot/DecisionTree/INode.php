<?php
namespace BlueFission\Automata\DecisionTree;

interface INode {
    public function getValue(): array;
    public function getChildren(): array;
    public function addChild(INode $child): void;
    public function evaluate(): int;
}