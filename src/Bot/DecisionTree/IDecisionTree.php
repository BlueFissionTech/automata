<?php
namespace BlueFission\Automata\DecisionTree;

interface IDecisionTree {
    public function getRoot(): INode;
    public function setRoot(INode $node): void;
    public function decide(IMethod $method): array;
}