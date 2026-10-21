<?php

namespace BlueFission\Examples\Cortex;

use BlueFission\{Arr, Str};
use BlueFission\Automata\Learning\{Experience, ITrainingAdapter, TrainingExample};

/**
 * Fixture-owned human label admission, separate from signed workflow rewards.
 * Conflicting approved labels abstain instead of guessing credit assignment.
 * Hosts must authenticate reviewers before constructing these admitted records.
 */
final class SessionProjection implements ITrainingAdapter
{
    /** Projection identity binds the one-dimensional observed sensor feature. */
    public function id(): string { return 'compound-session-label'; }
    /** Changing the feature or admission contract requires a new projection version. */
    public function version(): string { return '1'; }
    /** Project only one unambiguous approved target from retained session evidence. */
    public function project(Experience $experience): iterable
    {
        $labels = []; $source = null;
        foreach ($experience->outcomes() as $outcome) {
            $record = $outcome->toArray(); $observed = $outcome->observations();
            if ($record['source'] !== 'fixture-reviewer' || !$outcome->successful()
                || ($observed['approved'] ?? null) !== true || !Str::is($observed['label'] ?? null)) { continue; }
            $label = $observed['label'];
            if (!Arr::has(['observe', 'assist'], $label, true)) { continue; }
            $labels[$label] = true; $source ??= $outcome->id();
        }
        if (Arr::size($labels) !== 1) { return []; }
        $features = $experience->toArray()['context']['data']['model_features'] ?? null;
        if (!Arr::is($features)) { return []; }
        return [new TrainingExample($experience->id(), $source, $features, Arr::make($labels)->keys()->val()[0])];
    }
}
