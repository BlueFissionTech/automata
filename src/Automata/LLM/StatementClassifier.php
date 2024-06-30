<?php
namespace BlueFission\Automata\LLM;

use App\Business\Services\OpenAIService;
use App\Business\Prompts\StatementClassification;
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
    protected $openAiService;
    protected $modelManager;
    protected $modelFilePath;
    protected $pipeline;

    public function __construct()
    {
        $this->openAiService = new OpenAIService();
        $this->modelManager = new ModelManager();
        $this->modelFilePath = OPUS_ROOT . '/storage/models/statement_type_model.phpml';
        
        $naiveBayes = new NaiveBayes();
        $vectorizer = new TokenCountVectorizer(new WhitespaceTokenizer());
        $tfIdfTransformer = new TfIdfTransformer();

        $this->pipeline = new Pipeline( [
            $vectorizer,
            $tfIdfTransformer,
        ], $naiveBayes );
    }

    public function classify($input)
    {
        if (env('OPEN_AI_API_KEY')) {
            $prompt = new StatementClassification($input);
            $result = $this->openAiService->complete($prompt->prompt(), ['max_tokens'=>2, 'stop'=>' ']);
            if ($result) {
                $classification = $this->extractClassification($result);
                if ($classification) {
                    return $classification;
                }
            }
        
        } elseif ( strlen($input) > 120 ) {

            if (file_exists($this->modelFilePath)) {
                $loadedModel = $this->modelManager->restoreFromFile($this->modelFilePath);
                $this->pipeline = $loadedModel;
            } else {
                $this->trainModel();
                $this->modelManager->saveToFile($this->pipeline, $this->modelFilePath);
            }

            $classification = $this->pipeline->predict([$input]);
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
        if (!empty($result['choices'][0]['text'])) {
            $classification = strtolower(trim($result['choices'][0]['text']));
            if (in_array($classification, ['question', 'statement', 'stop'])) {
                return $classification;
            }
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

        $this->pipeline->train($trainSamples, $trainLabels);

        $predictedLabels = $this->pipeline->predict($testSamples);
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