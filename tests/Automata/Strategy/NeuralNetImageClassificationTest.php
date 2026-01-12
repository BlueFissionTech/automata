<?php
namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Strategy\NeuralNetImageClassification;
use PHPUnit\Framework\TestCase;

class NeuralNetImageClassificationTest extends TestCase
{
    private $nnClassifier;

    protected function setUp(): void
    {
        $this->nnClassifier = new NeuralNetImageClassification();
    }

    public function testTrain()
    {
        $samples = [
            array_fill(0, 784, 0.0),
            array_fill(0, 784, 1.0),
        ];
        $targets = [0, 1];
        $this->nnClassifier->train($samples, $targets, 0.2);

        $this->assertIsInt($this->nnClassifier->predict(array_fill(0, 784, 0.0)));
    }

    public function testPredict()
    {
        $samples = [
            array_fill(0, 784, 0.0),
            array_fill(0, 784, 1.0),
        ];
        $targets = [0, 1];
        $this->nnClassifier->train($samples, $targets, 0.2);

        $prediction = $this->nnClassifier->predict(array_fill(0, 784, 0.0));
        $this->assertIsInt($prediction);
    }

    public function testAccuracy()
    {
        $samples = [
            array_fill(0, 784, 0.0),
            array_fill(0, 784, 1.0),
        ];
        $targets = [0, 1];
        $this->nnClassifier->train($samples, $targets, 0.2);

        $accuracy = $this->nnClassifier->accuracy();
        $this->assertGreaterThanOrEqual(0, $accuracy);
    }

    public function testSaveLoadModel()
    {
        $samples = [
            array_fill(0, 784, 0.0),
            array_fill(0, 784, 1.0),
        ];
        $targets = [0, 1];
        $this->nnClassifier->train($samples, $targets, 0.2);

        $path = 'neural_net_model.ser';
        $this->nnClassifier->saveModel($path);
        $this->assertFileExists($path);

        $newNNClassifier = new NeuralNetImageClassification();
        $newNNClassifier->loadModel($path);
        $prediction = $newNNClassifier->predict(array_fill(0, 784, 0.0));
        $this->assertIsInt($prediction);

        unlink($path);
    }
}
