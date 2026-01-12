<?php

namespace BlueFission\Automata\Language;

use BlueFission\Automata\Comprehension\Frame;
use BlueFission\Automata\Comprehension\Scene;
use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\Comprehension\Log;
use BlueFission\Automata\Memory\IWorkingMemory;
use BlueFission\DevElation as Dev;

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
        $this->grammar = Dev::apply('language.reader.grammar', $grammar);
        $this->documenter = Dev::apply('language.reader.documenter', $documenter);
        Dev::do('language.reader.construct', [
            'grammar' => $this->grammar,
            'documenter' => $this->documenter,
        ]);
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

        $text = Dev::apply('language.reader.text', $text);
        $tokens = Dev::apply('language.reader.tokenize', $this->grammar->tokenize($text));
        $tree = $this->readTokens($tokens);
        Dev::do('language.reader.document_read', [
            'text' => $text,
            'tokens' => $tokens,
            'tree' => $tree,
        ]);

        return Dev::apply('language.reader.document_result', $tree);
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
        $tokens = Dev::apply('language.reader.tokens', $tokens);
        foreach ($tokens as $token) {
            $this->documenter->push($token);
        }

        $this->documenter->processStatements();

        $tree = Dev::apply('language.reader.tree_raw', $this->documenter->getTree());

        if (empty($tree)) {
            $ref = new \ReflectionClass($this->documenter);
            if ($ref->hasProperty('_currentStatement')) {
                $prop = $ref->getProperty('_currentStatement');
                $prop->setAccessible(true);
                $current = $prop->getValue($this->documenter);

                if (is_object($current)) {
                    $tree = Dev::apply('language.reader.tree_fallback', [$current]);
                }
            }
        }

        Dev::do('language.reader.tree_ready', ['tree' => $tree, 'tokens' => $tokens]);

        return Dev::apply('language.reader.tree', $tree);
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
        $statements = Dev::apply('language.reader.holoscene_statements', $statements);
        $memory = Dev::apply('language.reader.holoscene_memory', $memory);
        $scene = new Scene($memory);
        Dev::do('language.reader.holoscene_start', [
            'statements' => $statements,
            'episodeId' => $episodeId,
        ]);

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
        Dev::do('language.reader.holoscene_complete', [
            'episodeId' => $episodeId,
            'scene' => $scene,
        ]);
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

        $result = Dev::apply('language.reader.narrative', $log->compose());
        Dev::do('language.reader.narrate', ['log' => $log, 'result' => $result]);

        return $result;
    }
}
