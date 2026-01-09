<?php

declare(strict_types=1);

use BlueFission\DevElation;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Intent\Matcher;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Arr;

require __DIR__ . '/../../../vendor/autoload.php';

DevElation::up();

class SimpleIntentAnalyzer implements IAnalyzer
{
    public function analyze(string $input, Context $context, array $keywords): Arr
    {
        $scores = [];
        $lower  = strtolower($input);

        foreach ($keywords as $label => $phrases) {
            $score = 0.0;
            foreach ($phrases as $phrase) {
                if (str_contains($lower, strtolower($phrase['text']))) {
                    $score += (float)$phrase['weight'];
                }
            }
            $scores[$label] = $score;
        }

        if (!empty($scores)) {
            arsort($scores);
        }

        return Arr::make($scores);
    }
}

class DispatchSkill extends BaseSkill
{
    private string $last = '';

    public function execute(Context $context)
    {
        $this->last = (string)$context->get('message', '');
    }

    public function response(): string
    {
        return $this->last;
    }
}

$analyzer = new SimpleIntentAnalyzer();
$matcher  = new Matcher($analyzer);

// Define intents with keyword criteria.
$dispatchTruck = new Intent('dispatch_truck', 'Dispatch Truck', [
    'keywords' => [
        ['word' => 'truck', 'priority' => 2],
        ['word' => 'road',  'priority' => 1],
        ['word' => 'bridge','priority' => 1],
    ],
]);

$dispatchAirlift = new Intent('dispatch_airlift', 'Dispatch Airlift', [
    'keywords' => [
        ['word' => 'airlift',   'priority' => 2],
        ['word' => 'helicopter','priority' => 2],
        ['word' => 'chopper',   'priority' => 1],
    ],
]);

$statusCheck = new Intent('status_check', 'Status Check', [
    'keywords' => [
        ['word' => 'status', 'priority' => 2],
        ['word' => 'report', 'priority' => 1],
    ],
]);

// Define skills.
$truckSkill   = new DispatchSkill('truck_skill');
$airliftSkill = new DispatchSkill('airlift_skill');
$statusSkill  = new DispatchSkill('status_skill');

$matcher
    ->registerIntent($dispatchTruck)
    ->registerIntent($dispatchAirlift)
    ->registerIntent($statusCheck)
    ->registerSkill($truckSkill)
    ->registerSkill($airliftSkill)
    ->registerSkill($statusSkill)
    ->associate($dispatchTruck, $truckSkill)
    ->associate($dispatchAirlift, $airliftSkill)
    ->associate($statusCheck, $statusSkill);

$input = $argv[1] ?? 'Requesting helicopter airlift for flooded hospital';

$context = new Context();
$context->set('message', $input);

$scores = $matcher->match($input, $context);
$scoresArray = $scores ? $scores->val() : [];

$bestIntentLabel = null;
$bestScore = -1;
foreach ($scoresArray as $label => $score) {
    if ($score > $bestScore) {
        $bestScore = $score;
        $bestIntentLabel = $label;
    }
}

$bestIntent = $bestIntentLabel ? $matcher->getIntent($bestIntentLabel) : null;
$response   = $bestIntent ? $matcher->process($bestIntent, $context) : null;

echo json_encode([
    'input'    => $input,
    'scores'   => $scoresArray,
    'selected' => $bestIntentLabel,
    'response' => $response,
], JSON_PRETTY_PRINT) . PHP_EOL;

