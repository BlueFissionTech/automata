<?php
namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Strategy\Basic;
use PHPUnit\Framework\TestCase;

class BasicTest extends TestCase
{
    private $basic;

    protected function setUp(): void
    {
        $this->basic = new Basic();
    }

    public function testTrain()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->basic->train($samples, $labels);
        $this->assertNotEmpty($this->basic->predict('a'));
    }

    public function testPredict()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->basic->train($samples, $labels);

        $prediction = $this->basic->predict('a');
        $this->assertEquals('a', $prediction);
    }

    public function testAccuracy()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->basic->train($samples, $labels);

        $this->basic->predict('a');
        $this->basic->predict('b');
        $this->basic->predict('c');

        $accuracy = $this->basic->accuracy();
        $this->assertGreaterThan(0, $accuracy);
    }

    public function testSaveLoadModel()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->basic->train($samples, $labels);

        $path = 'basic_model.ser';
        $this->basic->saveModel($path);
        $this->assertFileExists($path);

        $newBasic = new Basic();
        $newBasic->loadModel($path);
        $prediction = $newBasic->predict('a');
        $this->assertEquals('a', $prediction);

        unlink($path);
    }
}
