<?php

require dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once __DIR__ . '/sim/Cell.php';
require_once __DIR__ . '/sim/Position.php';
require_once __DIR__ . '/sim/Grid.php';
require_once __DIR__ . '/sim/CellClassifier.php';
require_once __DIR__ . '/sim/ResponderPlayer.php';
require_once __DIR__ . '/sim/ResponderStrategy.php';
require_once __DIR__ . '/sim/GridworldEntity.php';

use BlueFission\Automata\Classification\Gateway;
use BlueFission\Automata\Feedback\Assessor;
use BlueFission\Automata\Feedback\FeedbackRegistry;
use BlueFission\Automata\Feedback\Strategies\LabelOverlapStrategy;
use BlueFission\Automata\Feedback\Strategies\TimeWindowMatchStrategy;
use BlueFission\Automata\GameTheory\PayoffMatrix;
use BlueFission\Automata\Goal\Initiative;
use BlueFission\Automata\Goal\Objective;
use BlueFission\Automata\Simulation\Simulation;
use Examples\DisasterResponse\Sim\Cell;
use Examples\DisasterResponse\Sim\CellClassifier;
use Examples\DisasterResponse\Sim\Grid;
use Examples\DisasterResponse\Sim\GridworldEntity;
use Examples\DisasterResponse\Sim\Position;
use Examples\DisasterResponse\Sim\ResponderPlayer;
use Examples\DisasterResponse\Sim\ResponderStrategy;

$args = $argv ?? [];
$seed = 7;
$ticks = 12;

foreach ($args as $arg) {
    if (strpos($arg, '--seed=') === 0) {
        $seed = (int)substr($arg, strlen('--seed='));
    }
    if (strpos($arg, '--ticks=') === 0) {
        $ticks = max(1, (int)substr($arg, strlen('--ticks=')));
    }
}

mt_srand($seed);

$cells = [
    '1,1' => new Cell('residential', ['people' => 2, 'damaged' => true]),
    '2,1' => new Cell('residential', ['people' => 1]),
    '3,1' => new Cell('road', ['blocked' => true]),
    '1,3' => new Cell('bridge', ['blocked' => true]),
    '2,3' => new Cell('hospital', ['supplies' => 2]),
    '3,3' => new Cell('warehouse', ['supplies' => 3, 'damaged' => true]),
];

$grid = new Grid(5, 5, $cells);

$payoffs = new PayoffMatrix();
$payoffs->setPayoff(['rescue'], [5]);
$payoffs->setPayoff(['clear'], [3]);
$payoffs->setPayoff(['repair'], [2.5]);
$payoffs->setPayoff(['deliver'], [2]);
$payoffs->setPayoff(['move'], [1]);

$player = new ResponderPlayer('responder-1', new Position(0, 0));
$strategy = new ResponderStrategy($payoffs);

$gateway = new Gateway();
$gateway->registerClassifier(new CellClassifier(), 'cell');

$initiative = new Initiative([
    'initiative_id' => 'drill-001',
    'name' => 'disaster_response_grid',
    'ttl' => 90,
]);

$initiative->addObjective(new Objective([
    'metric' => 'rescue',
    'target' => 3,
    'value' => 3,
    'type' => 'people',
    'operator' => '>=',
    'tags' => ['rescue', 'people'],
    'priority' => 0.9,
]));

$initiative->addObjective(new Objective([
    'metric' => 'clear',
    'target' => 2,
    'value' => 2,
    'type' => 'blocked',
    'operator' => '>=',
    'tags' => ['clear', 'blocked'],
    'priority' => 0.7,
]));

$initiative->addObjective(new Objective([
    'metric' => 'repair',
    'target' => 2,
    'value' => 2,
    'type' => 'damage',
    'operator' => '>=',
    'tags' => ['repair', 'damage'],
    'priority' => 0.6,
]));

$initiative->addObjective(new Objective([
    'metric' => 'deliver',
    'target' => 3,
    'value' => 3,
    'type' => 'supplies',
    'operator' => '>=',
    'tags' => ['deliver', 'supplies'],
    'priority' => 0.5,
]));

$assessor = new Assessor();
$assessor->addStrategy(new LabelOverlapStrategy());
$assessor->addStrategy(new TimeWindowMatchStrategy());

$feedback = new FeedbackRegistry();

$simulation = new Simulation($ticks);
$simulation->addEntity(new GridworldEntity(
    $grid,
    $player,
    $strategy,
    $initiative,
    $gateway,
    $assessor,
    $feedback
));

$initialState = [
    'progress' => [
        'rescue' => 0,
        'clear' => 0,
        'repair' => 0,
        'deliver' => 0,
    ],
    'feedback' => [],
];

$log = $simulation->run($initialState);
$timeline = [];

foreach ($log as $state) {
    $last = $state['last'] ?? [];
    $last['tick'] = $state['tick'] ?? null;
    $timeline[] = $last;
}

$final = !empty($log) ? $log[count($log) - 1] : [];

$output = [
    'seed' => $seed,
    'ticks' => $ticks,
    'grid' => ['width' => $grid->width(), 'height' => $grid->height()],
    'progress' => $final['progress'] ?? [],
    'feedback' => $final['feedback'] ?? [],
    'timeline' => $timeline,
];

echo json_encode($output, JSON_PRETTY_PRINT);
