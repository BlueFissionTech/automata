<?php
namespace BlueFission\Automata\ExpertSystem;

interface IReasoner
{
    public function infer(ExpertSystem $system, Fact $fact): array;
}