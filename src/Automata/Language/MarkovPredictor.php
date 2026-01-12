<?php
namespace BlueFission\Automata\Language;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\DevElation as Dev;

class MarkovPredictor {
    protected OrganizedCollection $states;
    protected OrganizedCollection $beginnings;

    public function __construct() {
        $this->states = new OrganizedCollection();
        $this->states->setMax(5000); // Set an appropriate max size for states
        $this->states->setDecay(true, 0.001); // Enable decay to manage the relevance of states

        $this->beginnings = new OrganizedCollection();
        $this->beginnings->setMax(500); // Smaller number for beginnings as it usually holds less data
        Dev::do('language.markov.construct', [
            'states' => $this->states,
            'beginnings' => $this->beginnings,
        ]);
    }

    public function addSentence($sentence) {
        $sentence = Dev::apply('language.markov.add_sentence', $sentence);
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
                $transitions->add($word, $word, 1);
            }
            $previousWord = $word;
        }
        Dev::do('language.markov.sentence_added', ['sentence' => $sentence, 'words' => $words]);
    }

    public function tokenize($sentence) {
        $sentence = Dev::apply('language.markov.tokenize_sentence', $sentence);
        $tokens = preg_split('/\s+/', strtolower($sentence));
        Dev::do('language.markov.tokenized', ['tokens' => $tokens]);
        return $tokens;
    }

    public function predictNextWord($currentWord) {
        $currentWord = Dev::apply('language.markov.predict_input', $currentWord);
        $tokens = $this->tokenize($currentWord);
        $currentWord = $tokens[0] ?? (string)$currentWord;
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
                    $word = Dev::apply('language.markov.predict_word', $word);
                    Dev::do('language.markov.predicted', ['word' => $word, 'current' => $currentWord]);
                    return $word;
                }
            }
        }
        return Dev::apply('language.markov.predict_none', null);
    }

    public function serializeModel() {
        // Serialize the model state
        $model = serialize(['states' => $this->states, 'beginnings' => $this->beginnings]);
        Dev::do('language.markov.serialize', ['data' => $model]);
        return $model;
    }

    public function deserializeModel($data) {
        // Deserialize the model state
        $data = unserialize($data);
        $this->states = $data['states'];
        $this->beginnings = $data['beginnings'];
        Dev::do('language.markov.deserialize', ['data' => $data]);
    }
}
