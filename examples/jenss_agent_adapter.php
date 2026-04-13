<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/tests/bootstrap.php';
require_once dirname(__DIR__) . '/src/Automata/GameTheory/Player.php';

use BlueFission\Automata\GameTheory\Player;
use BlueFission\Automata\Goal\Initiative;

$agent = new Player('Continuity Lead');
$strategy = new class {
    public function decide(Player $player): array
    {
        return [
            'action' => 'reroute',
            'confidence' => 0.82,
            'explanation' => 'Route B preserves continuity with acceptable delay.',
        ];
    }
};

$agent
    ->role('operator')
    ->scope('circumstantial')
    ->awareness('local')
    ->efficacy('can_make_now')
    ->addGoal(new Initiative(['name' => 'Maintain service']))
    ->addCriterion('continuity', 2, 'stability')
    ->addCriterion('safety', 3, 'risk')
    ->addStrategy('decision_tree', 1, 'routing')
    ->addStrategy('simulation', 2, 'planning')
    ->record('observation', ['event' => 'bridge closure'])
    ->setStrategy($strategy);

$decision = $agent->decide();

echo json_encode([
    'decision' => $decision,
    'summary' => $agent->explain(),
    'agent' => $agent->snapshot(),
], JSON_PRETTY_PRINT) . PHP_EOL;
