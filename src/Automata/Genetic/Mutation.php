<?php

namespace BlueFission\Automata\Genetic;

abstract class Mutation {
    public abstract function mutate($individual);
}
