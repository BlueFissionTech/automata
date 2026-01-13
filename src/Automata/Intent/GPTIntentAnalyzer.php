<?php
// GPTIntentAnalyzer.php
namespace BlueFission\Automata\Intent;

use BlueFission\Automata\Context;
use BlueFission\Automata\LLM\Reply;
use BlueFission\DevElation as Dev;

class LLMIntentAnalyzer implements IAnalyzer
{
    private $llm;

    public function __construct($llm)
    {
        if (!$llm) {
            throw new \Exception("Language model service is not registered.");
        }
        $this->_llm = Dev::apply('intent.llm.llm', $llm);
        Dev::do('intent.llm.construct', ['llm_intent_analyzer_construct' => $this]);
    }

    public function analyze(string $input, Context $context, array $intents): array
    {
        $scores = [];
        $input = Dev::apply('intent.llm.input', $input);
        $context = Dev::apply('intent.llm.context', $context);
        $intents = Dev::apply('intent.llm.intents', $intents);

        Dev::do('intent.llm.analyze', ['llm_analyze' => $input, 'context' => $context]);

        foreach ($intents as $intentName => $intent) {
            $criteria = $intent->getCriteria();
            $keywords = array_map(function ($keyword) {
                return $keyword['word'];
            }, $criteria['keywords']);

            // Prepare the prompt
            $prompt = "Rate the similarity of the following input to these keywords: \"$input\". Keywords: ";
            $prompt .= implode(', ', $keywords);
            $prompt .= '. Score from 0 to 1.';
            $prompt = Dev::apply('intent.llm.prompt', $prompt);
            Dev::do('intent.llm.prompt_event', ['llm_prompt' => $prompt]);

            // Get the GPT-3 completion
            $response = $this->_llm->complete($prompt);
            $response = Dev::apply('intent.llm.response', $response);

            $score = 0;
            $responseText = $this->extractResponseText($response);

            if ($responseText !== null) {
                $score = floatval($responseText);
                $score = Dev::apply('intent.llm.score', $score);
            }

            if ($score > 0) {
                $scores[$intent->getName()] = $score;
            }
        }

        $scores = Dev::apply('intent.llm.scores', $scores);
        Dev::do('intent.llm.result', ['llm_scores' => $scores]);

        return $scores;
    }

    private function extractResponseText($response): ?string
    {
        if ($response instanceof Reply) {
            if (!$response->success()) {
                return null;
            }

            return (string)$response->messages()->get(0);
        }

        if (is_array($response)) {
            if (!empty($response['choices'][0]['text'])) {
                return (string)$response['choices'][0]['text'];
            }

            if (!empty($response['choices'][0]['message']['content'])) {
                return (string)$response['choices'][0]['message']['content'];
            }

            if (!empty($response['completion'])) {
                return (string)$response['completion'];
            }

            if (!empty($response['message'])) {
                return (string)$response['message'];
            }

            if (!empty($response['text'])) {
                return (string)$response['text'];
            }
        }

        if (is_string($response) || is_numeric($response)) {
            return (string)$response;
        }

        return null;
    }
}
