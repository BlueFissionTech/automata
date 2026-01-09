<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Language\MarkovPredictor;

class MarkovPredictorTest extends TestCase
{
    private function buildPredictor(): MarkovPredictor
    {
        $predictor = new MarkovPredictor();
        $predictor->addSentence('hub road open');
        $predictor->addSentence('hub road closed');
        $predictor->addSentence('hospital supply delayed');
        $predictor->addSentence('hospital supply delivered');

        return $predictor;
    }

    public function testPredictNextWordDeterministicWithSeed(): void
    {
        $predictor = $this->buildPredictor();

        mt_srand(1234);
        $next1 = $predictor->predictNextWord('hub');

        mt_srand(1234);
        $next2 = $predictor->predictNextWord('hub');

        $this->assertSame($next1, $next2);
    }

    public function testSerializeAndDeserializePreservesBehavior(): void
    {
        $predictor = $this->buildPredictor();

        mt_srand(42);
        $before = $predictor->predictNextWord('hospital');

        $serialized = $predictor->serializeModel();

        $restored = new MarkovPredictor();
        $restored->deserializeModel($serialized);

        mt_srand(42);
        $after = $restored->predictNextWord('hospital');

        $this->assertSame($before, $after);
    }
}

