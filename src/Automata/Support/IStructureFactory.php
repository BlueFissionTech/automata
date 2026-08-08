<?php

namespace BlueFission\Automata\Support;

interface IStructureFactory
{
    public function arr(mixed $value = []): mixed;

    public function vec(mixed $value = []): mixed;

    public function dict(mixed $value = []): mixed;

    public function set(mixed $value = []): mixed;

    public function collection(mixed $value = []): mixed;

    public function values(mixed $value): array;

    public function fill(int $count, mixed $value): array;
}
