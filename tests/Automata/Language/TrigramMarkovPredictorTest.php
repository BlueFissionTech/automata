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

    public function testBulkTrainingHandlesModerateDialogueCatalogQuickly(): void
    {
        $predictor = new TrigramMarkovPredictor();
        $sentences = [];

        for ($i = 0; $i < 350; $i++) {
            $sentences[] = 'intent ' . ($i % 25) . ' option ' . ($i % 10) . ' response ' . $i;
        }

        $started = hrtime(true);
        $predictor->addSentences($sentences);
        $elapsed = (hrtime(true) - $started) / 1_000_000_000;

        $this->assertLessThan(1.0, $elapsed);
        $this->assertSame('option', $predictor->predictNextWord('intent 7'));
        $this->assertGreaterThan(0, $predictor->stateCount());
    }

    public function testTrainingHonorsConfiguredBounds(): void
    {
        $predictor = new TrigramMarkovPredictor([
            TrigramMarkovPredictor::CONFIG_MAX_STATES => 12,
            TrigramMarkovPredictor::CONFIG_MAX_BEGINNINGS => 4,
        ]);
        $sentences = [];

        for ($i = 0; $i < 80; $i++) {
            $sentences[] = 'topic ' . $i . ' branch ' . ($i % 5) . ' answer';
        }

        $predictor->train($sentences);

        $this->assertLessThanOrEqual(12, $predictor->stateCount());
        $this->assertLessThanOrEqual(4, $predictor->beginningCount());
    }
}
