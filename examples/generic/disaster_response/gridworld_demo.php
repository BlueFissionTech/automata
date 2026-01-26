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
use BlueFission\Cli\Args;
use BlueFission\Cli\Args\OptionDefinition;
use BlueFission\Cli\Util\Ansi;
use BlueFission\Cli\Util\Canvas;
use BlueFission\Cli\Util\Screen;
use BlueFission\Cli\Util\Tty;
use BlueFission\Str;
use Examples\DisasterResponse\Sim\Cell;
use Examples\DisasterResponse\Sim\CellClassifier;
use Examples\DisasterResponse\Sim\Grid;
use Examples\DisasterResponse\Sim\GridworldEntity;
use Examples\DisasterResponse\Sim\Position;
use Examples\DisasterResponse\Sim\ResponderPlayer;
use Examples\DisasterResponse\Sim\ResponderStrategy;

function parseOptions(array $args): array
{
    $parser = new Args();
    $parser->addOptions([
        new OptionDefinition('seed', [
            'short' => ['s'],
            'type' => 'int',
            'default' => 7,
            'description' => 'Random seed for the simulation.',
        ]),
        new OptionDefinition('ticks', [
            'short' => ['t'],
            'type' => 'int',
            'default' => 12,
            'description' => 'Number of simulation ticks.',
        ]),
        new OptionDefinition('verbose', [
            'short' => ['v'],
            'type' => 'bool',
            'default' => false,
            'description' => 'Render grid frames to the terminal.',
        ]),
        new OptionDefinition('delay', [
            'type' => 'int',
            'default' => 250,
            'description' => 'Tick delay in milliseconds.',
        ]),
        new OptionDefinition('animate', [
            'type' => 'bool',
            'description' => 'Animate frames in-place.',
        ]),
        new OptionDefinition('unicode', [
            'type' => 'bool',
            'default' => false,
            'description' => 'Use unicode glyphs for the grid.',
        ]),
        new OptionDefinition('ascii', [
            'type' => 'bool',
            'default' => false,
            'description' => 'Force ASCII glyphs for the grid.',
        ]),
        new OptionDefinition('color', [
            'type' => 'bool',
            'default' => true,
            'description' => 'Enable ANSI colors.',
        ]),
        new OptionDefinition('json', [
            'type' => 'bool',
            'description' => 'Emit JSON summary.',
        ]),
    ]);

    $parser->parse($args);
    $options = $parser->options();
    if (!empty($options['help'])) {
        echo $parser->usage() . PHP_EOL;
        exit(0);
    }

    $verbose = (bool)($options['verbose'] ?? false);
    $animate = array_key_exists('animate', $options) ? (bool)$options['animate'] : null;
    if ($animate === null) {
        $animate = $verbose;
    }

    $json = array_key_exists('json', $options) ? (bool)$options['json'] : null;
    if ($json === null) {
        $json = !$verbose;
    }

    $unicode = (bool)($options['unicode'] ?? false);
    if (!empty($options['ascii'])) {
        $unicode = false;
    }

    $colorEnabled = (bool)($options['color'] ?? true);
    $colorEnabled = $colorEnabled && Tty::isTty() && Ansi::supportsColors();

    return [
        'seed' => (int)($options['seed'] ?? 7),
        'ticks' => max(1, (int)($options['ticks'] ?? 12)),
        'verbose' => $verbose,
        'delay' => max(0, (int)($options['delay'] ?? 250)),
        'animate' => $animate,
        'unicode' => $unicode,
        'color' => $colorEnabled,
        'json' => $json,
    ];
}

function colorizeText(string $text, ?string $color = null, array $styles = [], bool $enabled = true): string
{
    if (!$enabled) {
        return $text;
    }

    return Ansi::colorize($text, $color, $styles, true);
}

function buildGridLines(array $snapshot, bool $useUnicode, bool $color): array
{
    $glyphs = [
        'agent' => $useUnicode ? "\u{1F9D1}" : 'A',
        'people' => $useUnicode ? "\u{1F465}" : 'P',
        'blocked' => $useUnicode ? "\u{26D4}" : 'X',
        'damage' => $useUnicode ? "\u{1F4A5}" : 'D',
        'supplies' => $useUnicode ? "\u{1F4E6}" : 'S',
        'road' => '.',
        'residential' => 'r',
        'hospital' => 'h',
        'bridge' => 'b',
        'warehouse' => 'w',
    ];

    $gridPlain = array_fill(0, $snapshot['height'], array_fill(0, $snapshot['width'], '.'));
    $gridColor = array_fill(0, $snapshot['height'], array_fill(0, $snapshot['width'], '.'));
    $cellIndex = [];
    foreach ($snapshot['cells'] as $cell) {
        $cellIndex[$cell['y'] . ',' . $cell['x']] = $cell;
    }

    for ($y = 0; $y < $snapshot['height']; $y++) {
        for ($x = 0; $x < $snapshot['width']; $x++) {
            $cell = $cellIndex[$y . ',' . $cell['x']] ?? null;
            if (!$cell) {
                $gridPlain[$y][$x] = '.';
                $gridColor[$y][$x] = '.';
                continue;
            }

            $symbol = $glyphs[$cell['type']] ?? '.';
            $style = ['dim'];
            $colorName = 'gray';

            if (!empty($cell['supplies'])) {
                $symbol = $glyphs['supplies'];
                $colorName = 'green';
                $style = ['bold'];
            }
            if (!empty($cell['damaged'])) {
                $symbol = $glyphs['damage'];
                $colorName = 'magenta';
                $style = ['bold'];
            }
            if (!empty($cell['blocked'])) {
                $symbol = $glyphs['blocked'];
                $colorName = 'red';
                $style = ['bold'];
            }
            if (!empty($cell['people'])) {
                $symbol = $glyphs['people'];
                $colorName = 'yellow';
                $style = ['bold'];
            }

            $gridPlain[$y][$x] = $symbol;
            $gridColor[$y][$x] = colorizeText($symbol, $colorName, $style, $color);
        }
    }

    $agent = $snapshot['agent'] ?? ['x' => null, 'y' => null];
    if ($agent['x'] !== null && $agent['y'] !== null) {
        $gridPlain[$agent['y']][$agent['x']] = $glyphs['agent'];
        $gridColor[$agent['y']][$agent['x']] = colorizeText($glyphs['agent'], 'cyan', ['bold'], $color);
    }

    $plainLines = [];
    $colorLines = [];
    foreach ($gridPlain as $rowIndex => $row) {
        $plainLines[] = implode(' ', $row);
        $colorLines[] = implode(' ', $gridColor[$rowIndex]);
    }

    return [$plainLines, $colorLines];
}

function buildStatusLine(array $state, bool $color): array
{
    $last = $state['last'] ?? [];
    $assessment = $last['assessment'] ?? [];
    $progress = $state['progress'] ?? [];
    $feedback = $state['feedback'] ?? [];

    $action = $last['action'] ?? 'none';
    $success = !empty($last['success']) ? 'yes' : 'no';
    $tick = $last['tick'] ?? $state['tick'] ?? '-';

    $plainParts = [];
    $plainParts[] = 'Tick ' . $tick;
    $plainParts[] = 'Action ' . $action;
    $plainParts[] = 'Success ' . $success;

    $colorParts = $plainParts;
    $colorParts[0] = colorizeText('Tick ' . $tick, 'bright_cyan', ['bold'], $color);
    $colorParts[2] = 'Success ' . colorizeText($success, $success === 'yes' ? 'green' : 'red', ['bold'], $color);

    if ($assessment) {
        $summary = 'Assess ' . ($assessment['matched'] ? 'match' : 'miss') . ' ' . round((float)($assessment['score'] ?? 0), 2);
        $plainParts[] = $summary;
        $colorParts[] = $summary;
    }

    if (!empty($progress)) {
        $summary = [];
        foreach ($progress as $metric => $value) {
            $summary[] = $metric . ':' . $value;
        }
        $line = 'Progress [' . implode(' ', $summary) . ']';
        $plainParts[] = $line;
        $colorParts[] = $line;
    }

    if (!empty($feedback)) {
        $summary = [];
        foreach ($feedback as $metric => $value) {
            $summary[] = $metric . ':' . round((float)$value, 2);
        }
        $line = 'Feedback [' . implode(' ', $summary) . ']';
        $plainParts[] = $line;
        $colorParts[] = $line;
    }

    return [implode(' | ', $plainParts), implode(' | ', $colorParts)];
}

function buildTagsLine(array $classification, bool $color): array
{
    if (empty($classification)) {
        return ['', ''];
    }

    $items = [];
    foreach ($classification as $tag) {
        $label = $tag['label'] ?? '';
        $score = isset($tag['score']) ? round((float)$tag['score'], 2) : 0.0;
        $items[] = $label . '(' . $score . ')';
    }

    $line = 'Top tags: ' . implode(', ', $items);
    return [$line, colorizeText($line, 'bright_white', [], $color)];
}

function buildLegendLine(bool $color): array
{
    $entries = [
        'A=Agent',
        'P=People',
        'X=Blocked',
        'D=Damage',
        'S=Supplies',
        'r=Residential',
        'h=Hospital',
        'b=Bridge',
        'w=Warehouse',
        '.=Road',
    ];

    $line = 'Legend: ' . implode(' ', $entries);
    return [$line, colorizeText($line, 'gray', ['dim'], $color)];
}

function buildFrameLines(array $state, array $options, bool $color): array
{
    $plain = [];
    $colored = [];

    $snapshot = $state['grid_snapshot'] ?? null;
    if ($snapshot) {
        [$gridPlain, $gridColored] = buildGridLines($snapshot, $options['unicode'], $color);
        $plain = array_merge($plain, $gridPlain);
        $colored = array_merge($colored, $gridColored);
        [$legendPlain, $legendColored] = buildLegendLine($color);
        $plain[] = $legendPlain;
        $colored[] = $legendColored;
    }

    [$statusPlain, $statusColored] = buildStatusLine($state, $color);
    $plain[] = $statusPlain;
    $colored[] = $statusColored;

    [$tagsPlain, $tagsColored] = buildTagsLine($state['last']['classification'] ?? [], $color);
    if ($tagsPlain !== '') {
        $plain[] = $tagsPlain;
        $colored[] = $tagsColored;
    }

    return [$plain, $colored];
}

function maxLineWidth(array $lines): int
{
    $width = 0;
    foreach ($lines as $line) {
        $width = max($width, Str::len($line));
    }

    return $width;
}

$options = parseOptions($argv ?? []);
$seed = $options['seed'];
$ticks = $options['ticks'];
$delay = $options['delay'];
$color = $options['color'];

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
$entity = new GridworldEntity(
    $grid,
    $player,
    $strategy,
    $initiative,
    $gateway,
    $assessor,
    $feedback
);
$simulation->addEntity($entity);

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

if ($options['verbose']) {
    $previousCanvas = null;
    $frameHeight = 0;

    if ($options['animate']) {
        echo Screen::hideCursor();
        register_shutdown_function(function () {
            echo Screen::showCursor();
        });
    }

    foreach ($log as $state) {
        [$plainLines, $colorLines] = buildFrameLines($state, $options, $color);
        $frameHeight = max($frameHeight, count($plainLines));

        if ($options['animate']) {
            $canvas = new Canvas(maxLineWidth($plainLines), count($plainLines));
            foreach ($plainLines as $index => $line) {
                $canvas->drawText(1, $index + 1, $line);
            }

            if ($previousCanvas === null) {
                echo Screen::clearScreen();
                echo Screen::moveCursor(1, 1);
                foreach ($colorLines as $line) {
                    echo Screen::clearLine() . $line . PHP_EOL;
                }
            } else {
                $diffs = $canvas->diffLines($previousCanvas);
                foreach ($diffs as $lineNumber => $line) {
                    if (!is_int($lineNumber)) {
                        continue;
                    }
                    $coloredLine = $colorLines[$lineNumber - 1] ?? '';
                    echo Screen::moveCursor(1, $lineNumber) . Screen::clearLine() . $coloredLine;
                }
            }

            $previousCanvas = $canvas;

            if ($delay > 0) {
                usleep($delay * 1000);
            }
        } else {
            foreach ($colorLines as $line) {
                echo $line . PHP_EOL;
            }
            echo PHP_EOL;
        }
    }

    if ($options['animate']) {
        echo Screen::moveCursor(1, $frameHeight + 1) . PHP_EOL;
    }
}

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

if ($options['json']) {
    echo json_encode($output, JSON_PRETTY_PRINT);
}
