<?php
namespace BlueFission\Automata\Expert;

interface IApproach
{
    public function execute(Expert $expert): bool;
}
