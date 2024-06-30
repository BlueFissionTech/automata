<?php
namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Strategy\KNearestPrediction;
use PHPUnit\Framework\TestCase;

class KNearestPredictionTest extends TestCase
{
    private $knn;

    protected function setUp(): void
    {
        $this->knn = new KNearestPrediction();
    }

    public function testTrain()
    {
        $data = [
            [1, 2], [2, 3], [3, 4],
            [6, 7], [7, 8], [8, 9]
        ];
        $labels = ['a', 'a', 'a', 'b', 'b', 'b'];
        $this->knn->train($data, $labels, 0.2);

        $this->assertNotEmpty($this->knn->predict([1, 2]));
    }

    public function testPredict()
    {
        $data = [
            [1, 2], [2, 3], [3, 4],
            [6, 7], [7, 8], [8, 9]
        ];
        $labels = ['a', 'a', 'a', 'b', 'b', 'b'];
        $this->knn->train($data, $labels, 0.2);

        $prediction = $this->knn->predict([1, 2]);
        $this->assertEquals('a', $prediction);

        $prediction = $this->knn->predict([7, 8]);
        $this->assertEquals('b', $prediction);
    }

    public function testAccuracy()
    {
        $data = [
            [1, 2], [2, 3], [3, 4],
            [6, 7], [7, 8], [8, 9]
        ];
        $labels = ['a', 'a', 'a', 'b', 'b', 'b'];
        $this->knn->train($data, $labels, 0.2);

        $accuracy = $this->knn->accuracy();
        $this->assertGreaterThan(0, $accuracy);
    }

    public function testSaveLoadModel()
    {
        $data = [
            [1, 2], [2, 3], [3, 4],
            [6, 7], [7, 8], [8, 9]
        ];
        $labels = ['a', 'a', 'a', 'b', 'b', 'b'];
        $this->knn->train($data, $labels, 0.2);

        $path = 'knn_model.ml';
        $this->knn->saveModel($path);
        $this->assertFileExists($path);

        $newKnn = new KNearestPrediction();
        $newKnn->loadModel($path);
        $prediction = $newKnn->predict([1, 2]);
        $this->assertEquals('a', $prediction);

        unlink($path);
    }
}
