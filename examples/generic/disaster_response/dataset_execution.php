<?php

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use BlueFission\Automata\Classification\Gateway;
use BlueFission\Automata\Classification\IClassifier;
use BlueFission\Automata\Classification\Result;
use BlueFission\Automata\Context;
use BlueFission\Cli\Args;
use BlueFission\Cli\Args\OptionDefinition;

class KeywordClassifier implements IClassifier
{
    private array $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function classify($input, Context $context, array $options = []): Result
    {
        $result = new Result();
        $text = normalizeText((string)($input['text'] ?? ''));

        foreach ($this->rules as $label => $keywords) {
            $hits = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $hits++;
                }
            }

            if ($hits > 0) {
                $score = min(1.0, 0.3 + ($hits * 0.15));
                $result->addTag($label, $score, ['hits' => $hits]);
            }
        }

        return $result;
    }

    public function accuracy(): float
    {
        return 0.0;
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

function normalizeText(string $text): string
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function extractFeatures(string $text): array
{
    $normalized = normalizeText($text);
    $tokens = $normalized === '' ? [] : explode(' ', $normalized);

    $urlCount = preg_match_all('~https?://~', $text, $matches);

    return [
        'word_count' => count($tokens),
        'char_count' => strlen($text),
        'url_count' => (int)$urlCount,
        'has_numbers' => (bool)preg_match('/\d/', $text),
    ];
}

function parseOptions(array $args): array
{
    $parser = new Args();
    $parser->addOptions([
        new OptionDefinition('limit', [
            'type' => 'int',
            'default' => 25,
            'description' => 'Limit dataset records.',
        ]),
        new OptionDefinition('use-mock', [
            'type' => 'bool',
            'default' => false,
            'description' => 'Force mock dataset usage.',
            'aliases' => ['use_mock'],
        ]),
        new OptionDefinition('dataset', [
            'type' => 'string',
            'default' => 'crisismmd',
            'description' => 'Dataset identifier to use.',
        ]),
        new OptionDefinition('verbose', [
            'short' => ['v'],
            'type' => 'bool',
            'default' => false,
            'description' => 'Print sample rows.',
        ]),
        new OptionDefinition('samples', [
            'type' => 'int',
            'default' => 3,
            'description' => 'Number of samples to print in verbose mode.',
        ]),
    ]);
    $parser->parse($args);
    $options = $parser->options();
    if (!empty($options['help'])) {
        echo $parser->usage() . PHP_EOL;
        exit(0);
    }

    return [
        'limit' => max(1, (int)($options['limit'] ?? 25)),
        'use_mock' => (bool)($options['use-mock'] ?? $options['use_mock'] ?? false),
        'dataset' => strtolower((string)($options['dataset'] ?? 'crisismmd')),
        'verbose' => (bool)($options['verbose'] ?? false),
        'samples' => max(1, (int)($options['samples'] ?? 3)),
    ];
}

function loadCrisisMmd(string $path, int $limit): array
{
    if (!is_file($path)) {
        return [];
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        return [];
    }

    $header = fgetcsv($handle, 0, "\t");
    if (!is_array($header)) {
        fclose($handle);
        return [];
    }

    $rows = [];
    while (($row = fgetcsv($handle, 0, "\t")) !== false) {
        $data = array_combine($header, $row);
        if (!is_array($data)) {
            continue;
        }
        $rows[] = [
            'id' => $data['tweet_id'] ?? '',
            'text' => $data['tweet_text'] ?? '',
            'label' => $data['label'] ?? 'unknown',
            'event' => $data['event_name'] ?? 'unknown',
        ];
        if (count($rows) >= $limit) {
            break;
        }
    }

    fclose($handle);
    return $rows;
}

$options = parseOptions($argv ?? []);
$repoRoot = dirname(__DIR__, 3);
$datasetsRoot = dirname($repoRoot) . DIRECTORY_SEPARATOR . 'datasets';

$datasetPath = $datasetsRoot . DIRECTORY_SEPARATOR . 'crisismmd_datasplit_all' . DIRECTORY_SEPARATOR . 'crisismmd_datasplit_all' . DIRECTORY_SEPARATOR . 'task_damage_text_img_dev.tsv';

$items = [];
$status = 'missing';
$datasetName = 'crisismmd';

if (!$options['use_mock'] && $options['dataset'] === 'crisismmd') {
    $items = loadCrisisMmd($datasetPath, $options['limit']);
    if (!empty($items)) {
        $status = 'ok';
    }
}

if ($options['use_mock'] || empty($items)) {
    $mock = require __DIR__ . '/mock_dataset.php';
    $items = array_map(function ($item) {
        return [
            'id' => $item['id'] ?? '',
            'text' => $item['text'] ?? '',
            'label' => $item['label'] ?? 'unknown',
            'event' => $item['region'] ?? 'mock',
        ];
    }, $mock);
    $items = array_slice($items, 0, $options['limit']);
    $status = 'mock';
    $datasetName = 'mock';
}

$rules = [
    'damage' => ['damage', 'collapsed', 'debris', 'destroyed', 'rubble'],
    'rescue' => ['rescue', 'help', 'trapped', 'evacuate', 'urgent'],
    'medical' => ['injured', 'hospital', 'medical', 'ambulance'],
    'infrastructure' => ['bridge', 'road', 'power', 'grid', 'pipeline'],
    'supplies' => ['food', 'water', 'supply', 'shelter'],
];

$gateway = new Gateway();
$gateway->registerClassifier(new KeywordClassifier($rules), 'keyword');

$tagCounts = [];
$labelCounts = [];
$featureStats = [];
$samples = [];

foreach ($items as $item) {
    $features = extractFeatures($item['text']);
    foreach ($features as $key => $value) {
        if (!isset($featureStats[$key])) {
            $featureStats[$key] = ['min' => $value, 'max' => $value, 'sum' => 0, 'count' => 0];
        }
        $featureStats[$key]['min'] = min($featureStats[$key]['min'], $value);
        $featureStats[$key]['max'] = max($featureStats[$key]['max'], $value);
        $featureStats[$key]['sum'] += is_bool($value) ? (int)$value : (int)$value;
        $featureStats[$key]['count']++;
    }

    $label = $item['label'] ?? 'unknown';
    $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;

    $result = $gateway->classify(['text' => $item['text']], ['context' => new Context()]);
    foreach ($result->tags() as $tag => $info) {
        $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
    }

    if ($options['verbose'] && count($samples) < $options['samples']) {
        $samples[] = [
            'id' => $item['id'] ?? '',
            'label' => $label,
            'text' => substr((string)$item['text'], 0, 140),
            'features' => $features,
            'tags' => $result->top(5),
        ];
    }
}

$normalization = [];
foreach ($featureStats as $key => $stats) {
    $avg = $stats['count'] > 0 ? $stats['sum'] / $stats['count'] : 0;
    $normalization[$key] = [
        'min' => $stats['min'],
        'max' => $stats['max'],
        'avg' => round($avg, 2),
    ];
}

$output = [
    'dataset' => $datasetName,
    'status' => $status,
    'source_path' => $datasetPath,
    'count' => count($items),
    'label_distribution' => $labelCounts,
    'tag_distribution' => $tagCounts,
    'feature_normalization' => $normalization,
];

if ($options['verbose']) {
    echo "Dataset={$datasetName} status={$status} count=" . count($items) . "\n";
    foreach ($samples as $sample) {
        echo "Sample {$sample['id']} label={$sample['label']}\n";
        echo "  text: {$sample['text']}\n";
        echo '  features: ' . json_encode($sample['features']) . "\n";
        foreach ($sample['tags'] as $tag) {
            $label = $tag['label'] ?? '';
            $score = isset($tag['score']) ? round((float)$tag['score'], 2) : 0.0;
            echo "  tag {$label} score={$score}\n";
        }
        echo "\n";
    }
}

echo json_encode($output, JSON_PRETTY_PRINT);
