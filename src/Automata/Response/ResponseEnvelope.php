<?php

namespace BlueFission\Automata\Response;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;

/** Fixed membership prevents later denominator changes from weakening release policy. */
final class ResponseEnvelope
{
    private readonly string $id;
    private readonly array $fragments;
    private readonly array $trace;

    public function __construct(string $id, array $fragments, array $trace = [])
    {
        $this->id = RecordSnapshot::identifier($id, 'response id');
        if (!Arr::check($fragments, 'array_is_list') || Arr::count($fragments) < 1 || Arr::count($fragments) > 128) {
            throw new InvalidArgumentException('An envelope requires a list of 1 to 128 fragments.');
        }
        $records = [];
        $weight = Num::make(0.0);
        foreach ($fragments as $fragment) {
            if (!$fragment instanceof ResponseFragment || isset($records[$fragment->id()])) {
                throw new InvalidArgumentException('Expected unique response fragments.');
            }
            $records[$fragment->id()] = $fragment->toArray();
            $weight->add($fragment->toArray()['weight']);
        }
        if (!Num::check($weight->val(), 'is_finite') || $weight->val() <= 0) {
            throw new InvalidArgumentException('Total fragment weight must be finite and positive.');
        }
        $visiting = $visited = [];
        $visit = function (string $id) use (&$visit, &$visiting, &$visited, $records): void {
            if (!isset($records[$id]) || isset($visiting[$id])) {
                throw new InvalidArgumentException('Response dependencies must exist and be acyclic.');
            }
            if (isset($visited[$id])) { return; }
            $visiting[$id] = true;
            foreach ($records[$id]['dependencies'] as $dependency) { $visit($dependency); }
            unset($visiting[$id]);
            $visited[$id] = true;
        };
        foreach ($fragments as $fragment) { $visit($fragment->id()); }
        $this->fragments = Arr::make($fragments)->values()->val();
        $this->trace = RecordSnapshot::canonical(RecordSnapshot::copy($trace, 4));
    }

    public function id(): string { return $this->id; }
    /** @return ResponseFragment[] */
    public function fragments(): array { return $this->fragments; }
    public function trace(): array { return $this->trace; }
    public function toArray(): array
    {
        return ['id' => $this->id, 'fragments' => Arr::make($this->fragments)
            ->map(static fn (ResponseFragment $fragment): array => $fragment->toArray())->values()->val(), 'trace' => $this->trace];
    }
}
