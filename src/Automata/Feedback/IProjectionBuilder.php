<?php

namespace BlueFission\Automata\Feedback;

interface IProjectionBuilder
{
    /**
     * @return Projection[]
     */
    public function buildProjections(): array;
}
