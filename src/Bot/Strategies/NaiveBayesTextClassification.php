<?php

namespace BlueFission\Bot\Strategies;

use Phpml\Classification\NaiveBayes;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Metric\Accuracy;
use Phpml\Dataset\ArrayDataset;
use Phpml\CrossValidation\RandomSplit;
use Phpml\Pipeline;

class NaiveBayesTextClassification extends Strategy
{
    private $classifier;
    private $vectorizer;
    private $transformer;

    public function __construct()
    {
        $this->classifier = new NaiveBayes();
        $this->vectorizer = new TokenCountVectorizer(new WhitespaceTokenizer());
        $this->transformer = new TfIdfTransformer();
        $this->pipeline = new Pipeline( [
            $this->vectorizer,
            $this->transformer,
        ], $this->classifier );
    }

    public function setPipeline(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    public function getPipeline()
    {
        return $this->pipeline;
    }

    public function train($samples, $labels, float $testSize = 0.2)
    {
        $splitDataset = new RandomSplit(new ArrayDataset($samples, $labels), $testSize);
        $trainSamples = $splitDataset->getTrainSamples();
        $trainLabels = $splitDataset->getTrainLabels();

        $this->testSamples = $splitDataset->getTestSamples();
        $this->testTargets = $splitDataset->getTestLabels();

        $this->pipeline->train($trainSamples, $trainLabels);
    }

    public function predict($input): string
    {
        $samples = [$input];

        $prediction = $this->pipeline->predict($samples);

        return $prediction[0];
    }

    public function accuracy(): float
    {
        $predictedLabels = $this->classifier->predictBatch($this->testSamples);
        return Accuracy::score($this->testTargets, $predictedLabels);
    }
}
