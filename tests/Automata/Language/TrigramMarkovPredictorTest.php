<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Language\TrigramMarkovPredictor;

class TrigramMarkovPredictorTest extends TestCase
{
    private function buildPredictor(): TrigramMarkovPredictor
    {
        $predictor = new TrigramMarkovPredictor();
        $predictor->addSentence('hub road open');
        $predictor->addSentence('hub road closed');
        $predictor->addSentence('hospital road closed');

        return $predictor;
    }

    public function testPredictNextWordDeterministicWithSeed(): void
    {
        $predictor = $this->buildPredictor();

        mt_srand(2024);
        $next1 = $predictor->predictNextWord('hub road');

        mt_srand(2024);
        $next2 = $predictor->predictNextWord('hub road');

        $this->assertSame($next1, $next2);
    }
}

