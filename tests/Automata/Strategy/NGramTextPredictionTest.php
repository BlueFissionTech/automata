<?php
namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Strategy\NGramTextPrediction;
use PHPUnit\Framework\TestCase;

class NGramTextPredictionTest extends TestCase
{
    private $_ngramPrediction;

    protected function setUp(): void
    {
        if (!class_exists(\Phpml\Tokenization\WhitespaceTokenizer::class)) {
            $this->markTestSkipped('php-ai/php-ml is not available; skipping NGramTextPrediction tests.');
        }

        $this->_ngramPrediction = new NGramTextPrediction();
    }

    public function testTrain()
    {
        $text = "This is a test text for ngram prediction. It is used to train the model.";
        $this->_ngramPrediction->train([$text], [], 0.2);

        $this->assertNotEmpty($this->_ngramPrediction->predict(['This', 'is']));
    }

    public function testPredict()
    {
        $text = "This is a test text for ngram prediction. It is used to train the model.";
        $this->_ngramPrediction->train([$text], [], 0.2);

        $prediction = $this->_ngramPrediction->predict(['This', 'is']);
        $this->assertIsString($prediction);
    }

    public function testAccuracy()
    {
        $text = "This is a test text for ngram prediction. It is used to train the model.";
        $this->_ngramPrediction->train([$text], [], 0.2);

        $accuracy = $this->_ngramPrediction->accuracy();
        $this->assertGreaterThan(0, $accuracy);
    }

    public function testSaveLoadModel()
    {
        $text = "This is a test text for ngram prediction. It is used to train the model.";
        $this->_ngramPrediction->train([$text], [], 0.2);

        $path = 'ngram_model.ml';
        $this->_ngramPrediction->saveModel($path);
        $this->assertFileExists($path);

        $newNGramPrediction = new NGramTextPrediction();
        $newNGramPrediction->loadModel($path);
        $prediction = $newNGramPrediction->predict(['This', 'is']);
        $this->assertIsString($prediction);

        unlink($path);
    }
}
