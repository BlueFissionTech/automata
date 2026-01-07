<?php
namespace BlueFission\Automata\Language;

use BlueFission\Automata\Collections\OrganizedCollection;

class MarkovPredictor {
    protected OrganizedCollection $states;
    protected OrganizedCollection $beginnings;

    public function __construct() {
        $this->states = new OrganizedCollection();
        $this->states->setMax(5000); // Set an appropriate max size for states
        $this->states->setDecay(true, 0.001); // Enable decay to manage the relevance of states

        $this->beginnings = new OrganizedCollection();
        $this->beginnings->setMax(500); // Smaller number for beginnings as it usually holds less data
    }

    public function addSentence($sentence) {
        $words = $this->tokenize($sentence);
        $previousWord = null;

        // Add the first word to beginnings if applicable
        if (count($words) > 0) {
            $this->beginnings->add($words[0]);
        }

        foreach ($words as $word) {
            if ($previousWord !== null) {
                if (!$this->states->has($previousWord)) {
                    $this->states->add(new OrganizedCollection(), $previousWord);
                    $this->states->get($previousWord)->setMax(500); // Limit each word's transitions
                }

                $transitions = $this->states->get($previousWord);
                $currentCount = $transitions->get($word) ?? 0;
                $transitions->add($currentCount + 1, $word);
            }
            $previousWord = $word;
        }
    }

    public function tokenize($sentence) {
        return preg_split('/\s+/', strtolower($sentence));
    }

    public function predictNextWord($currentWord) {
        if ($this->states->has($currentWord)) {
            $transitions = $this->states->get($currentWord);
            $raw = $transitions->contents();
            $weights = [];
            foreach ($raw as $key => $entry) {
                if (!is_array($entry) || !isset($entry['value'], $entry['weight'])) {
                    continue;
                }
                $weights[$entry['value']] = ($weights[$entry['value']] ?? 0) + $entry['weight'];
            }

            if (empty($weights)) {
                return null;
            }

            $total = array_sum($weights);
            $rand = mt_rand(0, $total - 1);

            foreach ($weights as $word => $count) {
                if (($rand -= $count) < 0) {
                    return $word;
                }
            }
        }
        return null; // No next word found
    }

    public function serializeModel() {
        // Serialize the model state
        return serialize(['states' => $this->states, 'beginnings' => $this->beginnings]);
    }

    public function deserializeModel($data) {
        // Deserialize the model state
        $data = unserialize($data);
        $this->states = $data['states'];
        $this->beginnings = $data['beginnings'];
    }
}
