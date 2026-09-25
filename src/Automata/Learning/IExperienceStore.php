<?php

namespace BlueFission\Automata\Learning;

interface IExperienceStore
{
    /** Save the current snapshot for an experience id. */
    public function save(Experience $experience): void;
    public function get(string $id): ?Experience;
    /** @return iterable<Experience> */
    public function experiences(): iterable;
}
