<?php
namespace BlueFission\Automata\GameTheory;

use BlueFission\Behavioral\StateMachine;
use BlueFission\Collections\Collection;
use BlueFission\DevElation as Dev;
use BlueFission\Func;
use BlueFission\Obj;
use BlueFission\Prototypes\Agent as PrototypeAgent;
use BlueFission\Prototypes\Proto;
use BlueFission\Str;

class Player extends Obj
{
    use StateMachine;
    use Proto {
        explain as protected prototypeExplain;
        snapshot as protected prototypeSnapshot;
    }
    use PrototypeAgent {
        decide as protected prototypeDecide;
    }

    private mixed $strategy = null;

    public function __construct($name)
    {
        parent::__construct();

        $name = Str::trim((string)$name);

        $this->protoId($name);
        $this->name($name);
        $this->kind('agent');
        $this->summary("agent[{$name}]");
    }

    public function getName(): string
    {
        return (string)$this->name();
    }

    public function setStrategy($strategy): self
    {
        $this->strategy = $strategy;

        $name = is_object($strategy) ? get_class($strategy) : Str::trim((string)$strategy);
        if ($name !== '' && !$this->hasStrategyDescriptor($name)) {
            $this->addStrategy($name);
        }

        return $this;
    }

    public function getStrategy()
    {
        return $this->strategy;
    }

    public function decide()
    {
        if (!$this->strategy) {
            return null;
        }

        $decision = $this->strategy->decide($this);
        $this->prototypeDecide($decision);

        Dev::do('automata.gametheory.player.decision_made', [
            'player' => $this,
            'decision' => $decision,
        ]);

        return $decision;
    }

    public function adoptDecision(mixed $decision): self
    {
        $this->prototypeDecide($decision);

        return $this;
    }

    public function addAction($stateName, $actionName, Func|callable $action, array $allowedStates, array $deniedStates)
    {
        $this->behavior($stateName, new Func(function() use ($actionName, $allowedStates, $deniedStates) {
            $this->allows($actionName, $allowedStates);
            $this->denies($actionName, $deniedStates);
        }));

        $this->action($actionName, $action instanceof Func ? $action : new Func($action));
    }

    public function addState($stateName)
    {
        $this->behavior($stateName, new Func(function() {}));
    }

    public function changeState($stateName)
    {
        if ($this->can($stateName)) {
            $this->dispatch($stateName);
        }
    }

    public function lastDecision(): mixed
    {
        return $this->prototypeSnapshot()['lastDecision'] ?? null;
    }

    public function explain(): string
    {
        $parts = [
            'agent[' . ($this->protoId() ?: $this->name() ?: 'unidentified') . ']',
            $this->role() !== '' ? "role={$this->role()}" : null,
            $this->scope() !== '' ? "scope={$this->scope()}" : null,
            $this->awareness() !== '' ? "awareness={$this->awareness()}" : null,
            $this->efficacy() !== '' ? "efficacy={$this->efficacy()}" : null,
            'goals=' . count($this->goals()),
            'criteria=' . count($this->criteria()),
            'strategies=' . count($this->strategies()),
            'history=' . count($this->history()),
            $this->strategy ? 'activeStrategy=' . (is_object($this->strategy) ? get_class($this->strategy) : (string)$this->strategy) : null,
        ];

        $summary = implode(' | ', (new Collection($parts))
            ->filter(fn ($part) => $part !== null && $part !== '')
            ->contents());
        $this->summary($summary);

        return $summary;
    }

    public function snapshot(): array
    {
        $snapshot = $this->prototypeSnapshot();
        $snapshot['role'] = $this->role();
        $snapshot['scope'] = $this->scope();
        $snapshot['awareness'] = $this->awareness();
        $snapshot['efficacy'] = $this->efficacy();
        $snapshot['autonomy'] = $this->autonomy();
        $snapshot['control'] = $this->control();
        $snapshot['goalCount'] = count($this->goals());
        $snapshot['criterionCount'] = count($this->criteria());
        $snapshot['strategyCount'] = count($this->strategies());
        $snapshot['historyCount'] = count($this->history());
        $snapshot['activeStrategy'] = $this->strategy
            ? (is_object($this->strategy) ? get_class($this->strategy) : (string)$this->strategy)
            : null;
        $snapshot['summary'] = $this->summary() ?: 'agent[' . ($this->protoId() ?: $this->name() ?: 'unidentified') . ']';

        return $snapshot;
    }

    private function hasStrategyDescriptor(string $name): bool
    {
        foreach ($this->strategies() as $strategy) {
            if (($strategy['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
}
