<?php

namespace BlueFission\Bot\Strategies;

use Phpml\Metric\Distance;
use Phpml\Classification\KNearestNeighbors;
use Phpml\Dataset\ArrayDataset;
use Phpml\Metric\Accuracy;

class KNearestPrediction extends Strategy
{
    private $knn;
    private $testSamples;
    private $testLabels;

    public function __construct(int $k = 3)
    {
        $this->knn = new KNearestNeighbors($k, new Distance\Euclidean());
    }

    public function train(array $data, array $labels, float $testSize = 0.2): void
    {
        $dataset = new ArrayDataset($data, $labels);
        list($trainSamples, $testSamples, $trainLabels, $testLabels) = $dataset->randomSplit($testSize);
        
        $this->testSamples = $testSamples;
        $this->testLabels = $testLabels;

        $this->knn->train($trainSamples, $trainLabels);
    }

    public function predict(array $features)
    {
        return $this->knn->predict($features);
    }

    public function accuracy(): float
    {
        $predictedLabels = [];
        foreach ($this->testSamples as $sample) {
            $predictedLabels[] = $this->knn->predict($sample);
        }

        return Accuracy::score($this->testLabels, $predictedLabels);
    }
}



// Example data and labels
// In a real scenario, you would preprocess your data to obtain numerical features
$bookData = [
    [1, 2, 3], // Features of book 1
    [4, 5, 6], // Features of book 2
    [7, 8, 9], // Features of book 3
];

$bookLabels = [
    'Book 1',
    'Book 2',
    'Book 3',
];

$bookRecommender = new Recommender();
$bookRecommender->train($bookData, $bookLabels);

// Example features for a new book
$newBookFeatures = [2, 3, 4];

$recommendedBook = $bookRecommender->recommend($newBookFeatures);
echo "Recommended book: " . $recommendedBook . PHP_EOL;
