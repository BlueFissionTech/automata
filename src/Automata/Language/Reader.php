<?php

namespace BlueFission\Automata\Language;

use BlueFission\Automata\Comprehension\Frame;
use BlueFission\Automata\Comprehension\Scene;
use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\Comprehension\Log;
use BlueFission\Automata\Memory\IWorkingMemory;

/**
 * Reader
 *
 * A lightweight façade around Grammar + Documenter that can:
 * - consume text or token streams and emit semantic Statements
 * - project those Statements into Scenes / Holoscenes
 * - generate a narrative Log from stored scenes
 *
 * This class is intentionally additive and does not change the
 * behavior of Interpreter, Grammar, or Documenter, so existing
 * consumers such as Synthetiq continue to work as-is.
 */
class Reader
{
    private ?Grammar $grammar;
    private Documenter $documenter;

    public function __construct(?Grammar $grammar, Documenter $documenter)
    {
        $this->grammar = $grammar;
        $this->documenter = $documenter;
    }

    /**
     * Read a raw document string using the configured Grammar,
     * producing an array of Statement-like nodes from the Documenter.
     *
     * @param string $text
     * @return array
     */
    public function readDocument(string $text): array
    {
        if (!$this->grammar) {
            throw new \RuntimeException('Reader requires a Grammar to read raw documents. Use readTokens() when providing pre-tokenized input.');
        }

        $tokens = $this->grammar->tokenize($text);

        return $this->readTokens($tokens);
    }

    /**
     * Consume a pre-tokenized stream (as emitted by Grammar::tokenize)
     * and return the Documenter's statement tree.
     *
     * @param array<int,array<string,mixed>> $tokens
     * @return array
     */
    public function readTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            $this->documenter->push($token);
        }

        $this->documenter->processStatements();

        $tree = $this->documenter->getTree();

        // If no completed statements were emitted, fall back to any
        // in-progress current statement. This keeps Reader usable
        // for partial or very short inputs without changing core
        // Documenter semantics used elsewhere (e.g., Synthetiq).
        if (empty($tree)) {
            $ref = new \ReflectionClass($this->documenter);
            if ($ref->hasProperty('_currentStatement')) {
                $prop = $ref->getProperty('_currentStatement');
                $prop->setAccessible(true);
                $current = $prop->getValue($this->documenter);

                if (is_object($current)) {
                    return [$current];
                }
            }
        }

        return $tree;
    }

    /**
     * Project an array of Statement objects into a Scene stored in a Holoscene.
     *
     * @param array $statements Array of Statement-like objects.
     * @param Holoscene $holoscene
     * @param IWorkingMemory $memory
     * @param string $episodeId
     */
    public function toHoloscene(array $statements, Holoscene $holoscene, IWorkingMemory $memory, string $episodeId): void
    {
        $scene = new Scene($memory);

        foreach ($statements as $statement) {
            if (!is_object($statement)) {
                continue;
            }

            // Statement is an Obj derivative; use dynamic field access.
            $fields = ['subject', 'behavior', 'object', 'indirect_object', 'relationship', 'context', 'modality'];
            $values = [];

            foreach ($fields as $field) {
                $value = null;

                if (method_exists($statement, 'field')) {
                    $value = $statement->field($field);
                } elseif (isset($statement->$field)) {
                    $value = $statement->$field;
                }

                if ($value !== null && $value !== '') {
                    $values[$field] = ['value' => $value, 'weight' => 1];
                }
            }

            if (count($values) === 0) {
                continue;
            }

            $frame = new Frame();
            $frame->addExperience(['values' => $values], null);
            $scene->addFrame($frame);
        }

        $holoscene->push($episodeId, $scene);
    }

    /**
     * Generate a simple narrative Log from all scenes stored in a Holoscene.
     *
     * @param Holoscene $holoscene
     * @param string|null $place
     * @return string
     */
    public function narrateHoloscene(Holoscene $holoscene, ?string $place = null): string
    {
        $holoscene->review();
        $assessment = $holoscene->assessment();

        $log = new Log();
        $log->setTime(date('Y-m-d H:i:s'));
        $log->setPlace($place ?? 'Unknown location');

        $seenEntities = [];

        foreach ($assessment as $episodeId => $entry) {
            $scene = $entry['value'] ?? null;
            if (!$scene instanceof Scene) {
                continue;
            }

            foreach ($scene->frames() as $frame) {
                $entities = $frame->extract();

                $subject = $entities['subject']['value'] ?? null;
                $behavior = $entities['behavior']['value'] ?? null;
                $object = $entities['object']['value'] ?? null;

                if ($subject && !isset($seenEntities[$subject])) {
                    $log->addEntity((string)$subject, 'Subject in narrative');
                    $seenEntities[$subject] = true;
                }
                if ($object && !isset($seenEntities[$object])) {
                    $log->addEntity((string)$object, 'Object in narrative');
                    $seenEntities[$object] = true;
                }

                if ($subject && $behavior) {
                    $fact = $subject . ' ' . $behavior;
                    if ($object) {
                        $fact .= ' ' . $object;
                    }
                    $log->addFact($fact . '.');
                }
            }
        }

        $log->setDescription('Narrative summary generated from Holoscene episodes.');

        return $log->compose();
    }
}
