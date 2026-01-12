<?php
namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Strategy\MarkovTextPrediction;
use PHPUnit\Framework\TestCase;

class MarkovTextPredictionTest extends TestCase
{
    private $_markovPrediction;

    protected function setUp(): void
    {
        $this->_markovPrediction = new MarkovTextPrediction();
    }

    public function testTrain()
    {
        $text = "This is a test text for markov prediction. It is used to train the model.";
        $this->_markovPrediction->train([$text], [], 0.2);

        $this->assertNotEmpty($this->_markovPrediction->predict('This'));
    }

    public function testPredict()
    {
        $text = "This is a test text for markov prediction. It is used to train the model.";
        $this->_markovPrediction->train([$text], [], 0.2);

        $prediction = $this->_markovPrediction->predict('This');
        $this->assertIsString($prediction);
    }

    public function testAccuracy()
    {
        $text = "This is a test text for markov prediction. It is used to train the model.";
        $this->_markovPrediction->train([$text], [], 0.2);

        $accuracy = $this->_markovPrediction->accuracy();
        $this->assertGreaterThan(0, $accuracy);
    }

    public function testSaveLoadModel()
    {
        $text = "This is a test text for markov prediction. It is used to train the model.";
        $this->_markovPrediction->train([$text], [], 0.2);

        $path = 'markov_model.ser';
        $this->_markovPrediction->saveModel($path);
        $this->assertFileExists($path);

        $newMarkovPrediction = new MarkovTextPrediction();
        $newMarkovPrediction->loadModel($path);
        $prediction = $newMarkovPrediction->predict('This');
        $this->assertIsString($prediction);

        unlink($path);
    }
}
