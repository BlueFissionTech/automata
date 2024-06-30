<?php
namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Strategy\Prediction;
use PHPUnit\Framework\TestCase;

class PredictionTest extends TestCase
{
    private $prediction;

    protected function setUp(): void
    {
        $this->prediction = new Prediction();
    }

    public function testTrain()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->prediction->train($samples, $labels);
        $this->assertNotEmpty($this->prediction->predict('a'));
    }

    public function testPredict()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->prediction->train($samples, $labels);

        $prediction = $this->prediction->predict('a');
        $this->assertEquals('a', $prediction);
    }

    public function testAccuracy()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->prediction->train($samples, $labels);

        $this->prediction->predict('a');
        $this->prediction->predict('b');
        $this->prediction->predict('c');

        $accuracy = $this->prediction->accuracy();
        $this->assertGreaterThan(0, $accuracy);
    }

    public function testSaveLoadModel()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->prediction->train($samples, $labels);

        $path = 'prediction_model.ser';
        $this->prediction->saveModel($path);
        $this->assertFileExists($path);

        $newPrediction = new Prediction();
        $newPrediction->loadModel($path);
        $prediction = $newPrediction->predict('a');
        $this->assertEquals('a', $prediction);

        unlink($path);
    }
}
