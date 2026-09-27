<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Func;

/** Host-defined strategy projection through the existing DevElation callable surface. */
final class CallbackTrainingAdapter implements ITrainingAdapter
{
    private Func $projector;

    public function __construct(private string $id, private string $version, callable $projector)
    {
        RecordSnapshot::identifier($id, 'projection id');
        RecordSnapshot::identifier($version, 'projection version');
        $this->projector = Func::make($projector);
    }

    public function id(): string { return $this->id; }
    public function version(): string { return $this->version; }
    public function project(Experience $experience): iterable { return ($this->projector)($experience); }
}
