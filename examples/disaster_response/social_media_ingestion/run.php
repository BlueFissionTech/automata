<?php

declare(strict_types=1);

use BlueFission\DevElation;
use BlueFission\Automata\Encoding\CategoricalEncoder;
use BlueFission\Automata\Normalization\NumericalScaler;
use BlueFission\Automata\Feature\InteractionFeatures;

require __DIR__ . '/../../../vendor/autoload.php';

DevElation::up();

$seed = 123;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--seed=')) {
        $seed = (int)substr($arg, strlen('--seed='));
    }
}
mt_srand($seed);

// Synthetic social-media-like posts in a flood scenario.
$posts = [
    [
        'id'      => 'p1',
        'source'  => 'twitter',
        'channel' => 'public',
        'text'    => 'Major flooding reported near the east bridge. Roads impassable.',
        'likes'   => 120,
        'shares'  => 40,
    ],
    [
        'id'      => 'p2',
        'source'  => 'facebook',
        'channel' => 'community',
        'text'    => 'Hospital A is running low on supplies, ambulances delayed.',
        'likes'   => 340,
        'shares'  => 120,
    ],
    [
        'id'      => 'p3',
        'source'  => 'twitter',
        'channel' => 'official',
        'text'    => 'Emergency management: avoid riverfront roads, use designated shelters.',
        'likes'   => 980,
        'shares'  => 360,
    ],
];

// Extract numeric features.
$likes   = array_column($posts, 'likes');
$shares  = array_column($posts, 'shares');
$lengths = array_map(static function (array $post): int {
    return strlen($post['text']);
}, $posts);

$likesScaler   = new NumericalScaler();
$sharesScaler  = new NumericalScaler();
$lengthScaler  = new NumericalScaler();

$likesScaled   = $likesScaler->fitTransform($likes);
$sharesScaled  = $sharesScaler->fitTransform($shares);
$lengthScaled  = $lengthScaler->fitTransform($lengths);

// Categorical encoding for channel and source.
$channels = array_column($posts, 'channel');
$sources  = array_column($posts, 'source');

$channelEncoder = new CategoricalEncoder(true, 'UNKNOWN');
$channelEncoder->fit($channels);
$channelVectors = $channelEncoder->transform($channels);

$sourceEncoder = new CategoricalEncoder(true, 'UNKNOWN');
$sourceEncoder->fit($sources);
$sourceVectors = $sourceEncoder->transform($sources);

// Build raw feature rows: scaled numerics + one-hot channels + one-hot sources.
$rawFeatureRows = [];
foreach ($posts as $i => $post) {
    $row = [
        $likesScaled[$i],
        $sharesScaled[$i],
        $lengthScaled[$i],
    ];

    $row = array_merge($row, $channelVectors[$i]->val());
    $row = array_merge($row, $sourceVectors[$i]->val());

    $rawFeatureRows[] = $row;
}

// Add interaction terms.
$interaction = new InteractionFeatures();
$featureVecs = $interaction->transform($rawFeatureRows);

$output = [];
foreach ($posts as $i => $post) {
    $output[] = [
        'id'       => $post['id'],
        'source'   => $post['source'],
        'channel'  => $post['channel'],
        'text'     => $post['text'],
        'features' => $featureVecs->get($i)->val(),
    ];
}

echo json_encode([
    'seed'   => $seed,
    'posts'  => $output,
], JSON_PRETTY_PRINT) . PHP_EOL;

