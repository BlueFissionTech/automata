<?php

declare(strict_types=1);

use BlueFission\DevElation;
use BlueFission\Automata\Sensory\Sense;
use BlueFission\Automata\Normalization\NumericalScaler;
use BlueFission\Automata\Encoding\CategoricalEncoder;
use BlueFission\Automata\Comprehension\Frame;
use BlueFission\Automata\Comprehension\Scene;
use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\Comprehension\Log;
use BlueFission\Automata\Memory\Abs2Memory;
use BlueFission\Automata\Intelligence;
use BlueFission\Automata\Strategy\IStrategy;

require __DIR__ . '/../../../vendor/autoload.php';

DevElation::up();

/**
 * Sense subclass that exposes prepared chunks for external use.
 */
class AnalysisSense extends Sense
{
    public function analyze(string $input): array
    {
        // Use the protected prepare method from Sense.
        return $this->prepare($input);
    }
}

/**
 * Simple coordination strategy: assigns a priority label based on severity
 * and type, using only the numeric feature vector.
 *
 * This implements IStrategy so it can be routed through Intelligence,
 * but does not rely on external ML libraries.
 */
class CoordinationPriorityStrategy implements IStrategy
{
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        // No-op for this simple rule-based strategy.
    }

    public function predict($input)
    {
        // Expect $input as [scaledSeverity, scaledChunkCount, ...oneHotOrgType].
        // We treat scaledSeverity > 0 as "high" and <= 0 as "normal".
        $scaledSeverity = $input[0] ?? 0.0;

        if ($scaledSeverity > 0) {
            return 'critical';
        }

        return 'normal';
    }

    public function accuracy(): float
    {
        // Placeholder; in a real implementation this would be computed.
        return 1.0;
    }

    public function saveModel(string $path): bool
    {
        return false;
    }

    public function loadModel(string $path): bool
    {
        return false;
    }
}

// Synthetic multi-org coordination events.
$events = [
    [
        'id'          => 'e1',
        'org'         => 'EOC',
        'dept'        => 'transportation',
        'type'        => 'road_closure',
        'severity'    => 3,
        'description' => 'Bridge 12 closed due to washout near Hospital A.',
    ],
    [
        'id'          => 'e2',
        'org'         => 'HospitalNetwork',
        'dept'        => 'logistics',
        'type'        => 'hospital_supply',
        'severity'    => 4,
        'description' => 'Hospital A requests 40 units of oxygen and 20 units of fuel.',
    ],
    [
        'id'          => 'e3',
        'org'         => 'ShelterOps',
        'dept'        => 'operations',
        'type'        => 'shelter_capacity',
        'severity'    => 2,
        'description' => 'Shelter B at 120% capacity; additional buses requested.',
    ],
];

// 1. Use Sense to analyze textual descriptions.
$sense = new AnalysisSense();

$chunkCounts = [];
foreach ($events as $i => $event) {
    $chunks = $sense->analyze($event['description']);
    $events[$i]['chunks'] = $chunks;
    $chunkCounts[$i] = count($chunks);
}

// 2. Build numeric and categorical features.
$severityRaw = array_column($events, 'severity');

$severityScaler = new NumericalScaler();
$chunkScaler    = new NumericalScaler();

$severityScaled = $severityScaler->fitTransform($severityRaw);
$chunkScaled    = $chunkScaler->fitTransform($chunkCounts);

// Encode org+type as a single categorical feature.
$orgTypes = array_map(
    static fn(array $e): string => $e['org'] . ':' . $e['type'],
    $events
);

$orgTypeEncoder = new CategoricalEncoder(true, 'UNKNOWN');
$orgTypeEncoder->fit($orgTypes);
$orgTypeVectors = $orgTypeEncoder->transform($orgTypes);

// Assemble feature vectors: [scaledSeverity, scaledChunkCount, one-hot orgType...]
$featureVectors = [];
foreach ($events as $i => $event) {
    $vec = [
        $severityScaled[$i],
        $chunkScaled[$i],
    ];
    $vec = array_merge($vec, $orgTypeVectors[$i]->val());
    $featureVectors[$i] = $vec;
}

// 3. Route through Intelligence with a simple strategy.
$intelligence = new Intelligence();
$strategy     = new CoordinationPriorityStrategy();
$intelligence->registerStrategy($strategy, 'coordination_priority');

$predictions = [];
foreach ($featureVectors as $i => $features) {
    $predictions[$events[$i]['id']] = $intelligence->predict($features);
}

// 4. Build Frames and a Scene with working memory (Abs2Memory).
$memory = new Abs2Memory();
$scene  = new Scene($memory);

foreach ($events as $event) {
    $frame = new Frame();

    $values = [
        'org'        => ['value' => $event['org'],        'weight' => 1],
        'dept'       => ['value' => $event['dept'],       'weight' => 1],
        'event_type' => ['value' => $event['type'],       'weight' => 1],
        'priority'   => ['value' => $predictions[$event['id']] ?? 'normal', 'weight' => 1],
    ];

    $frame->addExperience(['values' => $values], $event['id']);
    $scene->addFrame($frame);
}

// 5. Wrap the Scene in a Holoscene and create a Log.
$holoscene = new Holoscene();
$holoscene->push('eoc_episode', $scene);
$holoscene->review();

$log = new Log();
$log->setTime(date('Y-m-d H:i:s'));
$log->setPlace('Coastal County EOC');

// Collect tags and entities from events.
$tags    = [];
$orgSeen = [];

foreach ($events as $event) {
    $tags[] = $event['type'];
    if (!isset($orgSeen[$event['org']])) {
        $log->addEntity($event['org'], $event['dept']);
        $orgSeen[$event['org']] = true;
    }
    $log->addFact($event['description']);
}

foreach (array_unique($tags) as $tag) {
    $log->addTag($tag);
}

$log->setDescription('Coordinated response episode across transportation, hospitals, and shelters.');

$narrative = $log->compose();

// 6. Emit a JSON summary plus the narrative log.
$summary = [
    'events'      => $events,
    'predictions' => $predictions,
];

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
echo "\n---\n\n";
echo $narrative;

