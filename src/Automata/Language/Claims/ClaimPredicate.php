<?php

namespace BlueFission\Automata\Language\Claims;

use BlueFission\Arr;
use BlueFission\Str;

class ClaimPredicate extends ClaimValue
{
    public function operator(): string
    {
        return (string)$this->field('operator');
    }

    public function isKnown(): bool
    {
        return PredicateOperator::isKnown($this->operator());
    }

    public function children(): array
    {
        return Arr::make($this->field('children') ?? [])
            ->map(static fn (mixed $child): ClaimPredicate => $child instanceof ClaimPredicate
                ? $child
                : new ClaimPredicate(Arr::make($child)->toArray()))
            ->toArray();
    }

    public function structureIsValid(): bool
    {
        $children = $this->children();

        if (PredicateOperator::isLogical($this->operator())) {
            $validCount = $this->operator() === PredicateOperator::NOT
                ? Arr::size($children) === 1
                : Arr::size($children) > 0;

            if (!$validCount) {
                return false;
            }

            foreach ($children as $child) {
                if (!$child->structureIsValid()) {
                    return false;
                }
            }

            return true;
        }

        return Arr::size($children) === 0 && Str::trim((string)$this->field('path')) !== '';
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['children'] = Arr::make($this->children())
            ->map(static fn (ClaimPredicate $predicate): array => $predicate->toArray())
            ->toArray();

        return $data;
    }

    protected function defaults(): array
    {
        return [
            'operator' => PredicateOperator::EQUALS,
            'path' => null,
            'value' => null,
            'children' => [],
            'metadata' => [],
        ];
    }
}
