<?php
namespace BlueFission\Automata\LLM;

use BlueFission\Automata\LLM\Prompts\StatementClassification;
use Phpml\ModelManager;
use Phpml\Classification\NaiveBayes;
use Phpml\Dataset\ArrayDataset;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Metric\Accuracy;
use Phpml\CrossValidation\RandomSplit;
use Phpml\Pipeline;

class StatementClassifier
{
    protected $_llmClient;
    protected $_modelManager;
    protected $_modelFilePath;
    protected $_pipeline;

    public function __construct($llmClient = null, string $modelFilePath = null)
    {
        $this->_llmClient = $llmClient;
        $this->_modelManager = new ModelManager();
        $this->_modelFilePath = $modelFilePath;
        
        $naiveBayes = new NaiveBayes();
        $vectorizer = new TokenCountVectorizer(new WhitespaceTokenizer());
        $tfIdfTransformer = new TfIdfTransformer();

        $this->_pipeline = new Pipeline( [
            $vectorizer,
            $tfIdfTransformer,
        ], $naiveBayes );
    }

    public function classify($input)
    {
        if ($this->_llmClient && env('OPEN_AI_API_KEY')) {
            $prompt = new StatementClassification($input);
            $result = $this->_llmClient->complete($prompt->prompt(), ['max_tokens'=>2, 'stop'=>' ']);
            if ($result) {
                $classification = $this->extractClassification($result);
                if ($classification) {
                    return $classification;
                }
            }
        
        } elseif ( strlen($input) > 120 ) {

            if (file_exists($this->_modelFilePath)) {
                $loadedModel = $this->_modelManager->restoreFromFile($this->_modelFilePath);
                $this->_pipeline = $loadedModel;
            } else {
                $this->trainModel();
                if ($this->_modelFilePath) {
                    $this->_modelManager->saveToFile($this->_pipeline, $this->_modelFilePath);
                }
            }

            $classification = $this->_pipeline->predict([$input]);
            return $classification[0];
        } else {

            $statementsConfig = \App::instance()->configuration('nlp')['statements'];

            foreach ($statementsConfig as $class => $phrases) {
                foreach ($phrases as $phrase) {
                    similar_text(
                        preg_replace("/(?![.=$'€%-])\p{P}/u", "", strtolower($input)), 
                        preg_replace("/(?![.=$'€%-])\p{P}/u", "", strtolower($phrase)), 
                        $similarity);
                    if ($similarity > 85) {
                        return $class;
                    }
                }
            }
        }

        if ($this->isQuestion($input)) {
            return 'question';
        } elseif ($this->isStatement($input)) {
            return 'statement';
        } elseif ($this->shouldPause($input)) {
            return 'stop';
        }

        return 'unknown';
    }

    protected function extractClassification($result)
    {
        $text = null;

        if ($result instanceof Reply) {
            $text = $result->messages()->get(0);
        } elseif (is_array($result)) {
            if (!empty($result['choices'][0]['text'])) {
                $text = $result['choices'][0]['text'];
            } elseif (!empty($result['choices'][0]['message']['content'])) {
                $text = $result['choices'][0]['message']['content'];
            } elseif (!empty($result['completion'])) {
                $text = $result['completion'];
            } elseif (!empty($result['message'])) {
                $text = $result['message'];
            } elseif (!empty($result['text'])) {
                $text = $result['text'];
            }
        } elseif (is_string($result)) {
            $text = $result;
        }

        if ($text === null) {
            return null;
        }

        $classification = strtolower(trim((string)$text));
        if (in_array($classification, ['question', 'statement', 'stop'], true)) {
            return $classification;
        }

        return null;
    }

    protected function trainModel()
    {
        $samples = []; // Add your training samples here
        $labels = []; // Add corresponding labels for the samples here
        $statementsConfig = \App::instance()->configuration('nlp')['statements'];
        $samples = array_merge($statementsConfig['question'], $statementsConfig['statement'], $statementsConfig['stop']);
        $labels = array_merge(
            array_fill(0, count($statementsConfig['question']), 'question'),
            array_fill(0, count($statementsConfig['statement']), 'statement'),
            array_fill(0, count($statementsConfig['stop']), 'stop')
        );

        $dataset = new ArrayDataset($samples, $labels);

        $sampleSet = $dataset->getSamples();
        $targetSet = $dataset->getTargets();

        $splitDataset = new RandomSplit(new ArrayDataset($samples, $labels), '0.2');
        $trainSamples = $splitDataset->getTrainSamples();
        $trainLabels = $splitDataset->getTrainLabels();

        $testSamples = $splitDataset->getTestSamples();
        $testTargets = $splitDataset->getTestLabels();

        $this->_pipeline->train($trainSamples, $trainLabels);

        $predictedLabels = $this->_pipeline->predict($testSamples);
        $accuracy = Accuracy::score($testTargets, $predictedLabels);

        // $this->naiveBayes->train($sampleSet, $targetSet);
    }

    protected function isQuestion($text)
    {
        return substr(trim($text), -1) === '?' || preg_match('/^(what|when|where|why|how|which|who|whose)\b/i', $text);
    }

    protected function isStatement($text)
    {
        return !preg_match('/[?.!]\s*$/u', trim($text));
    }

    protected function shouldPause($text)
    {
        return preg_match('/\b(stop|pause|later|right now)\b/i', $text);
    }
}
