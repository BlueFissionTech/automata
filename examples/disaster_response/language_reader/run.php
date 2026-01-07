<?php

declare(strict_types=1);

use BlueFission\DevElation;
use BlueFission\Automata\Language\Documenter;
use BlueFission\Automata\Language\Reader;
use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\Memory\Abs2Memory;

require __DIR__ . '/../../../vendor/autoload.php';

DevElation::up();

$input = $argv[1] ?? 'HospitalA requests oxygen.';

// 1. Configure a minimal documenter that understands simple
// subject-verb-object sentences in disaster-response language.
$documenter = new Documenter();

$documenter->addRule(['T_OPERATOR'], function (array $cmd, $statement): void {
    $statement->field('behavior', $cmd['match']);
});

$documenter->addRule(['T_ENTITY'], function (array $cmd, $statement): void {
    if (!$statement->field('subject')) {
        $statement->field('subject', $cmd['match']);
    } elseif (!$statement->field('object')) {
        $statement->field('object', $cmd['match']);
    }
});

$reader = new Reader(null, $documenter);

// 2. Tokenize the input into a very small custom token format that
// matches what Documenter expects.
$operators = ['requests', 'needs', 'sends', 'evacuates', 'closes', 'reopens'];

// For this focused example, extract a simple
// subject-verb-object triple from the input.
if (preg_match('/^(.+?)\s+(requests|needs|sends|evacuates|closes|reopens)\s+(.+?)[.!?]?$/i', $input, $matches)) {
    $subjectText = trim($matches[1]);
    $operatorText = strtolower($matches[2]);
    $objectText = trim($matches[3]);
} else {
    // Fallback: treat the whole sentence as a subject with a
    // generic behavior and no explicit object.
    $subjectText = trim($input);
    $operatorText = 'describes';
    $objectText = '';
}

$tokens = [
    [
        'match' => $subjectText,
        'classifications' => ['T_ENTITY'],
        'expects' => [
            'T_ENTITY' => ['T_OPERATOR', 'T_PUNCTUATION'],
        ],
    ],
    [
        'match' => $operatorText,
        'classifications' => ['T_OPERATOR'],
        'expects' => [
            'T_OPERATOR' => ['T_ENTITY', 'T_PUNCTUATION'],
        ],
    ],
];

if ($objectText !== '') {
    $tokens[] = [
        'match' => $objectText,
        'classifications' => ['T_ENTITY'],
        'expects' => [
            'T_ENTITY' => ['T_PUNCTUATION'],
        ],
    ];
}

$tokens[] = [
    'match' => '.',
    'classifications' => ['T_PUNCTUATION'],
    'expects' => [
        'T_PUNCTUATION' => ['T_ENTITY', 'T_OPERATOR'],
    ],
];

// 3. Read tokens into statements, then project into a Holoscene.
$statements = $reader->readTokens($tokens);

$holoscene = new Holoscene();
$memory = new Abs2Memory();

$reader->toHoloscene($statements, $holoscene, $memory, 'episode_language_reader');

// 4. Generate a narrative from the Holoscene.
$narrative = $reader->narrateHoloscene($holoscene, 'Coastal County EOC');

$summary = [
    'input' => $input,
    'statements' => array_map(
        static function ($statement): array {
            return [
                'subject' => $statement->field('subject'),
                'behavior' => $statement->field('behavior'),
                'object' => $statement->field('object'),
            ];
        },
        $statements
    ),
];

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
echo "\n---\n\n";
echo $narrative;
