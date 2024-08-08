<?php
// KeywordIntentAnalyzer.php
namespace BlueFission\Automata\Intent;

use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\ModelManager;
use BlueFission\Arr;

class KeywordIntentAnalyzer implements IAnalyzer
{
    private $_intentClassifier;
    private $_modelDirPath;

    public function __construct(NaiveBayesTextClassification $intentClassifier, string $modelDirPath)
    {
        $this->_intentClassifier = $intentClassifier;
        $this->_modelDirPath = $modelDirPath;
    }

    public function analyze(string $input, Context $context, array $intents): Arr
    {
        $scores = [];

        // Tokenize the input into words and convert to lowercase
        $inputWords = preg_split('/\s+/', strtolower($input));
        $inputWordCount = count($inputWords);

        $samples = [];
        $labels = [];

        foreach ($intents as $intentName => $intent) {
            $criteria = $intent->getCriteria();
            // Calculate the score for this intent based on keywords, context, and other criteria.
            $score = 0;

            foreach ($criteria['keywords'] as $keyword) {
                $keywordLower = strtolower($keyword['word']);

                $samples[] = $keyword['word'];
                $labels[] = $intent->getLabel();
                
                foreach ($inputWords as $inputWord) {
                    $similarityPercent = 0;
                    similar_text($inputWord, $keywordLower, $similarityPercent);

                    // Convert the similarity percentage to a value between 0 and 1
                    $similarityScore = $similarityPercent / 100;

                    // Calculate the multiplier based on the input word count
                    $multiplier = 1 / $inputWordCount;
                    $multiplier = $multiplier*$keyword['priority'];

                    // Increase the score based on the similarity and multiplier
                    // $score += $keyword['priority'] * $similarityScore * $multiplier;
                    if ( $similarityScore > $score ) {
                        $score = $similarityScore*$multiplier;
                    }
                }
            }

            $scores[$intent->getLabel()] = $score;
        }

        $modelFilePath = $this->_modelDirPath . '/intent_model.phpml';
        $modelManager = new ModelManager();
        if ( file_exists($modelFilePath) ) {
            $loadedPipeline = $modelManager->restoreFromFile($modelFilePath);

            $this->_intentClassifier->setPipeline($loadedPipeline);
        } else {
            $this->_intentClassifier->train($samples, $labels);

            if ( !file_exists($this->_modelDirPath) ) {
                mkdir($this->_modelDirPath);
            }

            $modelManager->saveToFile($this->_intentClassifier->getPipeline(), $modelFilePath);
        }

        $classification = $this->_intentClassifier->predict($input);
        if ( !isset($scores[$classification]) ) {
            $scores[$classification] = 0;
        }

        $scores[$classification] = $this->classificationBonus($scores[$classification], .1);

        if ( !empty($scores) ) {
            arsort($scores);
        }

        return Arr::make($scores);
    }

    private function classificationBonus($current_score, $bonus_percentage) {
        // Ensure current_score and bonus_percentage are within the valid range
        $current_score = max(0, min(1, $current_score));
        $bonus_percentage = max(0, min(1, $bonus_percentage));

        // Calculate the new score
        $new_score = $current_score + (1 - $current_score) * $bonus_percentage;

        // Return the new score
        return $new_score;
    }
}
