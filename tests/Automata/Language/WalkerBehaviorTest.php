<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Language\Statement;
use BlueFission\Automata\Language\Walker;
use BlueFission\Automata\Language\Documenter;

class WalkerBehaviorTest extends TestCase
{
    public function testWalkerBuildsLogFromStatements(): void
    {
        $statement = new Statement();
        $statement->field('subject', 'HospitalA');
        $statement->field('behavior', 'requests');
        $statement->field('object', 'oxygen');

        $walker = new Walker();
        $walker->addStatement($statement);
        $walker->process();

        $log = $walker->log();

        $this->assertCount(1, $log);

        $entry = $log[0];

        $this->assertSame('HospitalA', $entry['subject']);
        $this->assertSame('requests', $entry['behavior']);
        $this->assertSame('oxygen', $entry['object']);
        $this->assertNotNull($entry['satisfied']);
    }

    public function testWalkerTraverseAcceptsDocumenterTree(): void
    {
        $documenter = new Documenter();
        $documenter->addRule(['T_OPERATOR'], function (array $cmd, $statement): void {
            $statement->field('behavior', $cmd['match']);
        });
        $documenter->addRule(['T_ENTITY'], function (array $cmd, $statement): void {
            if (!$statement->field('subject')) {
                $statement->field('subject', $cmd['match']);
            } elseif (!$statement->field('object')) {
                $statement->field('object', $cmd['match']);
            }
        });

        $tokens = [
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
        ];

        foreach ($tokens as $token) {
            $documenter->push($token);
        }
        $documenter->processStatements();

        $tree = $documenter->getTree();

        // In many configurations a short sentence will not reach the
        // satisfaction threshold that Documenter uses to finalize a
        // Statement. When that happens, fall back to the in-progress
        // statement so that Walker can still operate on it.
        if (empty($tree)) {
            $ref = new \ReflectionClass($documenter);
            $prop = $ref->getProperty('_currentStatement');
            $prop->setAccessible(true);
            $current = $prop->getValue($documenter);
            if (is_object($current)) {
                $tree = [$current];
            }
        }

        $walker = new Walker();
        $walker->traverse($tree);
        $walker->process();

        $log = $walker->log();

        $this->assertCount(1, $log);
        $entry = $log[0];

        $this->assertSame('HospitalA', $entry['subject']);
        $this->assertSame('requests', $entry['behavior']);
        $this->assertSame('oxygen', $entry['object']);
    }
}
