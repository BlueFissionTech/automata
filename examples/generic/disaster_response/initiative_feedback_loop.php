<?php

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use BlueFission\Automata\Goal\Initiative;
use BlueFission\Automata\Goal\Objective;
use BlueFission\Automata\Goal\Condition;
use BlueFission\Automata\Goal\CriterionType;
use BlueFission\Automata\Goal\ComparisonOperator;
use BlueFission\Automata\Feedback\Assessor;
use BlueFission\Automata\Feedback\Observation;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Feedback\FeedbackRegistry;
use BlueFission\Automata\Feedback\Strategies\LabelOverlapStrategy;
use BlueFission\Automata\Feedback\Strategies\TimeWindowMatchStrategy;
use BlueFission\Automata\Feedback\Strategies\ContextSimilarityStrategy;

$initiative = new Initiative(['name' => 'Disaster Response', 'ttl' => 120]);

$initiative->addObjective(new Objective([
    'type' => CriterionType::BEHAVIOR,
    'operator' => ComparisonOperator::IS,
    'value' => 'roads_cleared',
    'priority' => 0.8,
    'tags' => ['infrastructure', 'damage'],
]));

$initiative->addCondition(new Condition([
    'type' => CriterionType::TIME,
    'operator' => ComparisonOperator::AT_LEAST,
    'value' => '60',
    'priority' => 0.5,
    'tags' => ['time'],
]));

$projections = $initiative->buildProjections();

$assessor = new Assessor();
$assessor->addStrategy(new LabelOverlapStrategy());
$assessor->addStrategy(new TimeWindowMatchStrategy());
$assessor->addStrategy(new ContextSimilarityStrategy());

$registry = new FeedbackRegistry();

$observations = [
    new Observation([
        'tags' => ['damage', 'infrastructure'],
        'context' => ['region' => 'north'],
    ]),
    new Observation([
        'tags' => ['time'],
        'context' => ['region' => 'north'],
    ]),
];

foreach ($projections as $projection) {
    foreach ($observations as $observation) {
        $assessment = $assessor->assess($projection, $observation);
        $signal = $assessment->matched()
            ? FeedbackSignal::positive($assessment->score())
            : FeedbackSignal::negative(0.1);

        $registry->apply('initiative:' . $initiative->field('name'), $signal);
        echo "Assessment {$assessment->strategy()} score={$assessment->score()}\n";
    }
}

echo "Feedback score: " . $registry->score('initiative:' . $initiative->field('name')) . "\n";
