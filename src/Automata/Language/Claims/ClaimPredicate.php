<?php

namespace BlueFission\Automata\Language\Claims;

use BlueFission\Arr;

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
        return array_map(
            static fn (mixed $child): ClaimPredicate => $child instanceof ClaimPredicate
                ? $child
                : new ClaimPredicate(Arr::make($child)->toArray()),
            Arr::make($this->field('children') ?? [])->toArray()
        );
    }

    public function structureIsValid(): bool
    {
        $children = $this->children();

        if (PredicateOperator::isLogical($this->operator())) {
            $validCount = $this->operator() === PredicateOperator::NOT
                ? count($children) === 1
                : count($children) > 0;

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

        return count($children) === 0 && trim((string)$this->field('path')) !== '';
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['children'] = array_map(
            static fn (ClaimPredicate $predicate): array => $predicate->toArray(),
            $this->children()
        );

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
