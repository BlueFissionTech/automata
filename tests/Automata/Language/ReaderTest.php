<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Language\Documenter;
use BlueFission\Automata\Language\Reader;
use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\Memory\Abs2Memory;

class ReaderTest extends TestCase
{
    private function createSimpleDocumenter(): Documenter
    {
        $documenter = new Documenter();

        // Operator populates the behavior of the statement.
        $documenter->addRule(['T_OPERATOR'], function (array $cmd, $statement): void {
            $statement->field('behavior', $cmd['match']);
        });

        // Entity populates subject first, then object.
        $documenter->addRule(['T_ENTITY'], function (array $cmd, $statement): void {
            if (!$statement->field('subject')) {
                $statement->field('subject', $cmd['match']);
            } elseif (!$statement->field('object')) {
                $statement->field('object', $cmd['match']);
            }
        });

        return $documenter;
    }

    private function simpleTokens(): array
    {
        // A minimal token stream representing
        // "HospitalA requests oxygen."
        return [
            [
                'match' => 'HospitalA',
                'classifications' => ['T_ENTITY'],
                'expects' => [
                    'T_ENTITY' => ['T_OPERATOR', 'T_PUNCTUATION'],
                ],
            ],
            [
                'match' => 'requests',
                'classifications' => ['T_OPERATOR'],
                'expects' => [
                    'T_OPERATOR' => ['T_ENTITY', 'T_PUNCTUATION'],
                ],
            ],
            [
                'match' => 'oxygen',
                'classifications' => ['T_ENTITY'],
                'expects' => [
                    'T_ENTITY' => ['T_PUNCTUATION'],
                ],
            ],
            [
                'match' => '.',
                'classifications' => ['T_PUNCTUATION'],
                'expects' => [
                    'T_PUNCTUATION' => ['T_ENTITY', 'T_OPERATOR'],
                ],
            ],
        ];
    }

    public function testReaderMapsTokensToStatementRoles(): void
    {
        $documenter = $this->createSimpleDocumenter();
        $reader = new Reader(null, $documenter);

        $statements = $reader->readTokens($this->simpleTokens());

        $this->assertNotEmpty($statements);

        $statement = $statements[0];

        $this->assertSame('HospitalA', $statement->field('subject'));
        $this->assertSame('requests', $statement->field('behavior'));
        $this->assertSame('oxygen', $statement->field('object'));
    }

    public function testReaderProjectsStatementsIntoHoloscene(): void
    {
        $documenter = $this->createSimpleDocumenter();
        $reader = new Reader(null, $documenter);

        $tokens = $this->simpleTokens();
        $statements = $reader->readTokens($tokens);

        $holoscene = new Holoscene();
        $memory = new Abs2Memory();

        $reader->toHoloscene($statements, $holoscene, $memory, 'episode_language_reader');

        $holoscene->review();
        $assessment = $holoscene->assessment();

        $this->assertArrayHasKey('episode_language_reader', $assessment);

        $sceneEntry = $assessment['episode_language_reader'];
        $scene = $sceneEntry['value'] ?? null;

        $this->assertNotNull($scene);

        $frames = $scene->frames();
        $this->assertNotEmpty($frames);

        $frame = $frames[0];
        $entities = $frame->extract();

        $this->assertArrayHasKey('subject', $entities);
        $this->assertArrayHasKey('behavior', $entities);
        $this->assertArrayHasKey('object', $entities);

        $this->assertSame('HospitalA', $entities['subject']['value']);
        $this->assertSame('requests', $entities['behavior']['value']);
        $this->assertSame('oxygen', $entities['object']['value']);
    }

    public function testReaderNarratesHolosceneProducesLog(): void
    {
        $documenter = $this->createSimpleDocumenter();
        $reader = new Reader(null, $documenter);

        $tokens = $this->simpleTokens();
        $statements = $reader->readTokens($tokens);

        $holoscene = new Holoscene();
        $memory = new Abs2Memory();

        $reader->toHoloscene($statements, $holoscene, $memory, 'episode_language_reader');

        $narrative = $reader->narrateHoloscene($holoscene, 'Coastal County');

        $this->assertStringContainsString('##Scene', $narrative);
        $this->assertStringContainsString('Coastal County', $narrative);
        $this->assertStringContainsString('HospitalA requests oxygen.', $narrative);
    }
}

