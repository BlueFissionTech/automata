<?php

namespace BlueFission\Automata\Language;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;

class TrigramMarkovPredictor {
    public const CONFIG_MAX_STATES = 'max_states';
    public const CONFIG_MAX_BEGINNINGS = 'max_beginnings';
    public const CONFIG_MAX_TRANSITIONS = 'max_transitions';

    protected array $states = [];
    protected array $beginnings = [];
    protected int $maxStates;
    protected int $maxBeginnings;
    protected int $maxTransitions;

    /**
     * Configure bounded trigram storage for lightweight local prediction.
     *
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = []) {
        $this->maxStates = $this->normalizeLimit($config[self::CONFIG_MAX_STATES] ?? 10000, 10000);
        $this->maxBeginnings = $this->normalizeLimit($config[self::CONFIG_MAX_BEGINNINGS] ?? 1000, 1000);
        $this->maxTransitions = $this->normalizeLimit($config[self::CONFIG_MAX_TRANSITIONS] ?? 500, 500);

        Dev::do('language.trigram.construct', [
            'max_states' => $this->maxStates,
            'max_beginnings' => $this->maxBeginnings,
            'max_transitions' => $this->maxTransitions,
        ]);
    }

    /**
     * Add one sentence while preserving the legacy training entrypoint.
     */
    public function addSentence($sentence): self {
        return $this->addSentences([$sentence]);
    }

    /**
     * Add many sentences without per-transition collection sorting.
     *
     * @param iterable<int|string,mixed> $sentences
     */
    public function addSentences(iterable $sentences): self {
        $added = 0;

        foreach ($sentences as $sentence) {
            if ($this->addTokenSequence($this->tokenize($sentence))) {
                $added++;
            }
        }

        $this->prune();

        Dev::do('language.trigram.sentences_added', [
            'count' => $added,
            'states' => $this->stateCount(),
            'beginnings' => $this->beginningCount(),
        ]);

        return $this;
    }

    /**
     * Alias bulk training for strategy-style callers.
     *
     * @param iterable<int|string,mixed> $sentences
     */
    public function train(iterable $sentences): self {
        return $this->addSentences($sentences);
    }

    /**
     * Normalize a sentence into lowercase word tokens.
     *
     * @return array<int,string>
     */
    public function tokenize($sentence): array {
        $sentence = Dev::apply('language.trigram.tokenize_sentence', $sentence);
        $normalized = Str::make((string)$sentence)
            ->lower()
            ->trim()
            ->replacePattern('/\s+/', ' ');

        $tokens = [];
        foreach ($normalized->split(' ')->toArray() as $token) {
            if (Val::isNotEmpty($token)) {
                $tokens[] = (string)$token;
            }
        }

        Dev::do('language.trigram.tokenized', ['tokens' => $tokens]);

        return $tokens;
    }

    /**
     * Predict the next word from the last two words in the provided input.
     */
    public function predictNextWord($sentence) {
        $sentence = Dev::apply('language.trigram.predict_input', $sentence);
        $words = $this->tokenize($sentence);
        $count = Arr::count($words);

        if ($count < 2) {
            return Dev::apply('language.trigram.predict_none', null);
        }

        $previousTwoWords = $words[$count - 2] . ' ' . $words[$count - 1];

        if (!Arr::hasKey($this->states, $previousTwoWords)) {
            return Dev::apply('language.trigram.predict_none', null);
        }

        $nextWords = $this->states[$previousTwoWords];
        $total = $this->sumWeights($nextWords);

        if ($total < 1) {
            return Dev::apply('language.trigram.predict_none', null);
        }

        $rand = mt_rand(1, $total);

        foreach ($nextWords as $word => $weight) {
            $rand -= $weight;
            if ($rand <= 0) {
                $word = Dev::apply('language.trigram.predict_word', $word);
                Dev::do('language.trigram.predicted', ['word' => $word, 'context' => $previousTwoWords]);
                return $word;
            }
        }

        return Dev::apply('language.trigram.predict_none', null);
    }

    /**
     * Return the learned trigram state table.
     *
     * @return array<string,array<string,int>>
     */
    public function states(): array {
        return Arr::make($this->states)->toArray();
    }

    /**
     * Return tracked sentence-start contexts.
     *
     * @return array<string,int>
     */
    public function beginnings(): array {
        return Arr::make($this->beginnings)->toArray();
    }

    /**
     * Count trained trigram contexts.
     */
    public function stateCount(): int {
        return Arr::count($this->states);
    }

    /**
     * Count tracked beginning contexts.
     */
    public function beginningCount(): int {
        return Arr::count($this->beginnings);
    }

    /**
     * Clear trained state while preserving configured bounds.
     */
    public function reset(): self {
        $this->states = [];
        $this->beginnings = [];

        return $this;
    }

    /**
     * Add transitions from an already-tokenized sentence.
     *
     * @param array<int,string> $words
     */
    protected function addTokenSequence(array $words): bool {
        $count = Arr::count($words);
        if ($count < 3) {
            return false;
        }

        $this->rememberBeginning($words[0] . ' ' . $words[1]);

        for ($i = 2; $i < $count; $i++) {
            $trigram = $words[$i - 2] . ' ' . $words[$i - 1];
            $nextWord = $words[$i];

            if (!Arr::hasKey($this->states, $trigram)) {
                $this->states[$trigram] = [];
            }

            $this->states[$trigram][$nextWord] = ($this->states[$trigram][$nextWord] ?? 0) + 1;
            $this->pruneTransitions($trigram);
        }

        return true;
    }

    /**
     * Track a starting two-word context for possible future generation.
     */
    protected function rememberBeginning(string $context): void {
        $this->beginnings[$context] = ($this->beginnings[$context] ?? 0) + 1;
    }

    /**
     * Enforce global state and beginning limits after a bulk operation.
     */
    protected function prune(): void {
        while (Arr::count($this->states) > $this->maxStates) {
            foreach ($this->states as $key => $value) {
                unset($this->states[$key]);
                break;
            }
        }

        while (Arr::count($this->beginnings) > $this->maxBeginnings) {
            foreach ($this->beginnings as $key => $value) {
                unset($this->beginnings[$key]);
                break;
            }
        }
    }

    /**
     * Enforce per-context transition limits by dropping the lowest-weight terms.
     */
    protected function pruneTransitions(string $trigram): void {
        if (Arr::count($this->states[$trigram]) <= $this->maxTransitions) {
            return;
        }

        asort($this->states[$trigram]);

        while (Arr::count($this->states[$trigram]) > $this->maxTransitions) {
            foreach ($this->states[$trigram] as $word => $weight) {
                unset($this->states[$trigram][$word]);
                break;
            }
        }
    }

    /**
     * Sum integer transition weights without requiring collection conversion.
     *
     * @param array<string,int> $weights
     */
    protected function sumWeights(array $weights): int {
        $total = 0;

        foreach ($weights as $weight) {
            $total += (int)$weight;
        }

        return $total;
    }

    /**
     * Normalize a user-supplied positive integer limit.
     */
    protected function normalizeLimit(mixed $value, int $fallback): int {
        if (!Num::is($value)) {
            return $fallback;
        }

        $limit = (int)$value;

        return $limit > 0 ? $limit : $fallback;
    }
}
