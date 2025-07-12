<?php

namespace BlueFission\Automata\Language;

use BlueFission\Automata\Collections\OrganizedCollection;

class TrigramMarkovPredictor {
    protected $states;
    protected $beginnings;

    public function __construct() {
        $this->states = new OrganizedCollection();
        $this->states->setMax(10000); // Set max states to keep
        $this->states->setDecay(true, 0.001); // Enable decay with a specific rate
        $this->beginnings = new OrganizedCollection();
        $this->beginnings->setMax(1000); // Manage the size of beginnings similarly
    }

    public function addSentence($sentence) {
        $words = $this->tokenize($sentence);
        if (count($words) < 3) {
            return; // Skip sentences that are too short to form a trigram
        }

        // Add the first word sequence to beginnings for potential initial states
        $this->beginnings->add($words[0] . ' ' . $words[1]);

        for ($i = 2; $i < count($words); $i++) {
            $trigram = $words[$i - 2] . ' ' . $words[$i - 1];
            $nextWord = $words[$i];

            if (!$this->states->has($trigram)) {
                $this->states->add([], $trigram);
            }
            $currentData = $this->states->get($trigram);
            if (!isset($currentData[$nextWord])) {
                $currentData[$nextWord] = 0;
            }
            $currentData[$nextWord]++;
            $this->states->add($currentData, $trigram);
        }
    }

    public function tokenize($sentence) {
        // Simple tokenizer (consider improving or using a library for better tokenization)
        return preg_split('/\s+/', strtolower($sentence));
    }

    public function predictNextWord($sentence) {
        $previousTwoWords = implode(' ', array_slice($this->tokenize($sentence), -2));

        if ($this->states->has($previousTwoWords)) {
            $nextWords = $this->states->get($previousTwoWords);
            $total = array_sum($nextWords);
            $rand = mt_rand(0, $total - 1);

            foreach ($nextWords as $word => $count) {
                if (($rand -= $count) < 0) {
                    return $word;
                }
            }
        }
        return null; // No next word found if no suitable transition exists
    }
}
