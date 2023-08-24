<?php
namespace BlueFission\Automata\DecisionTree;

interface IMethod {
    public function traverse(INode $root): array;
}