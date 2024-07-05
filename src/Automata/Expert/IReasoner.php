<?php
namespace BlueFission\Automata\Expert;

interface IReasoner
{
    public function infer(Expert $system, Fact $fact): array;
}