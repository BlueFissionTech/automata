<?php
namespace BlueFission\Automata\LLM;

use Framework\Business\Services\OpenAIService;

class PhraseAnalyzer {
    private $openAIService;
    private $questionWords = array("who", "what", "when", "where", "why", "how", "which", "whose", "whom");

    public function __construct($openAIService) {
        $this->openAIService = $openAIService;
    }

    public function analyzePhrase($phrase) {
        $apiKey = env('OPEN_AI_API_KEY');

        if (!empty($apiKey)) {
            try {
                $response = $this->openAIService->complete("Classify the following phrase as 'dialogue' or 'inquiry': \"$phrase\"");
                $classification = strtolower(trim($response['choices'][0]['text']));

                if ($classification == 'dialogue' || $classification == 'inquiry') {
                    return $classification;
                }
            } catch (Exception $e) {
                // Do nothing, fallback to basic logic
            }
        }

        return $this->basicPhraseAnalysis($phrase);
    }

    private function basicPhraseAnalysis($phrase) {
        $phrase = trim(strtolower($phrase));

        if (substr($phrase, -1) == "?") {
            return 'inquiry';
        }

        $words = explode(" ", $phrase);
        if (in_array($words[0], $this->questionWords)) {
            return 'inquiry';
        }

        return 'dialogue';
    }
}
