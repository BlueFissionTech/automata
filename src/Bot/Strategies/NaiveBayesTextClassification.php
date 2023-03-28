<?php

namespace BlueFission\Bot\Strategies;

use Phpml\Classification\NaiveBayes;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Metric\Accuracy;
use Phpml\Dataset\RandomSplit;

class NaiveBayesTextClassification extends Strategy
{
    private $classifier;
    private $vectorizer;
    private $transformer;
    private $testSamples;
    private $testTargets;

    public function __construct()
    {
        $this->classifier = new NaiveBayes();
        $this->vectorizer = new TokenCountVectorizer(new WhitespaceTokenizer());
        $this->transformer = new TfIdfTransformer();
    }

    public function setClassifier(NaiveBayes $classifier)
    {
        $this->classifier = $classifier;
    }

    public function setVectorizer(TokenCountVectorizer $vectorizer)
    {
        $this->vectorizer = $vectorizer;
    }

    public function setTransformer(TfIdfTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function getClassifier()
    {
        return $this->classifier;
    }

        public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $splitDataset = new RandomSplit($samples, $labels, $testSize);
        $trainSamples = $splitDataset->getTrainSamples();
        $trainLabels = $splitDataset->getTrainLabels();

        $this->testSamples = $splitDataset->getTestSamples();
        $this->testTargets = $splitDataset->getTestLabels();

        $this->vectorizer->fit($trainSamples);
        $this->vectorizer->transform($trainSamples);

        $this->transformer->fit($trainSamples);
        $this->transformer->transform($trainSamples);

        $this->classifier->train($trainSamples, $trainLabels);
    }

    public function predict(string $input): string
    {
        $samples = [$input];
        $this->vectorizer->transform($samples);
        $this->transformer->transform($samples);

        return $this->classifier->predict($samples[0]);
    }

    public function accuracy(): float
    {
        $predictedLabels = $this->classifier->predictBatch($this->testSamples);
        return Accuracy::score($this->testTargets, $predictedLabels);
    }
}
