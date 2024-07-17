<?php
// GPTIntentAnalyzer.php
namespace BlueFission\Automata\Intent;

use BlueFission\Automata\Context;
use BlueFission\Automata\LLM\Clients\IClient;

class LLMIntentAnalyzer implements IAnalyzer
{
    private $llm;

    public function __construct(IClient $llm)
    {
        if (!$llm) {
            throw new \Exception("Language model service is not registered.");
        }
        $this->_llm = $llm;
    }

    public function analyze(string $input, Context $context, array $intents): array
    {
        $scores = [];

        foreach ($intents as $intentName => $intent) {
            $criteria = $intent->getCriteria();
            $keywords = array_map(function ($keyword) {
                return $keyword['word'];
            }, $criteria['keywords']);

            // Prepare the prompt
            $prompt = "Rate the similarity of the following input to these keywords: \"$input\". Keywords: ";
            $prompt .= implode(', ', $keywords);
            $prompt .= '. Score from 0 to 1.';

            // Get the GPT-3 completion
            $response = $this->_llm->complete($prompt);

            $score = 0;

            if ($response->success()) {
                // Get the score from the response
                $score = floatval($response->messages()->first());
            }

            if ($score > 0) {
                $scores[$intent->getName()] = $score;
            }
        }

        return $scores;
    }
}
