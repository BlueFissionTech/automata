<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BlueFission\Automata\Language\MarkovPredictor;
use BlueFission\Automata\Language\TrigramMarkovPredictor;

/**
 * Markov language example in the disaster logistics domain.
 *
 * Trains unigram and trigram Markov predictors on simple status
 * sentences and shows deterministic next-word predictions.
 */

echo "=== Markov Logistics Language Example ===\n\n";

$sentences = [
    'hub road open',
    'hub road closed',
    'hospital supply delayed',
    'hospital supply delivered',
    'shelter intake rising',
    'shelter intake critical',
];

$unigram = new MarkovPredictor();
foreach ($sentences as $s) {
    $unigram->addSentence($s);
}

$trigram = new TrigramMarkovPredictor();
foreach ($sentences as $s) {
    $trigram->addSentence($s);
}

mt_srand(123);
$nextHub = $unigram->predictNextWord('hub');
echo "Unigram Markov: after 'hub' -> '$nextHub'\n";

mt_srand(456);
$nextHospital = $unigram->predictNextWord('hospital');
echo "Unigram Markov: after 'hospital' -> '$nextHospital'\n\n";

mt_srand(789);
$nextHubRoad = $trigram->predictNextWord('hub road');
echo "Trigram Markov: after 'hub road' -> '$nextHubRoad'\n";

mt_srand(1011);
$nextShelterIntake = $trigram->predictNextWord('shelter intake');
echo "Trigram Markov: after 'shelter intake' -> '$nextShelterIntake'\n";

echo "\nExample completed.\n";

