<?php

namespace BlueFission\Bot\Genetic;

abstract class Mutation {
    public abstract function mutate($individual);
}
