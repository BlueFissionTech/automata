<?php
namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Strategy\Pattern;
use PHPUnit\Framework\TestCase;

class PatternTest extends TestCase
{
    private $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Pattern();
    }

    public function testTrain()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->pattern->train($samples, $labels);
        $this->assertNotEmpty($this->pattern->predict('a'));
    }

    public function testPredict()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->pattern->train($samples, $labels);

        $prediction = $this->pattern->predict('a');
        $this->assertEquals('b', $prediction);
    }

    public function testAccuracy()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->pattern->train($samples, $labels);

        $this->pattern->predict('a');
        $this->pattern->predict('b');
        $this->pattern->predict('c');

        $accuracy = $this->pattern->accuracy();
        $this->assertGreaterThan(0, $accuracy);
    }

    public function testSaveLoadModel()
    {
        $samples = [['a', 'b', 'c']];
        $labels = ['d'];
        $this->pattern->train($samples, $labels);

        $path = 'pattern_model.ser';
        $this->pattern->saveModel($path);
        $this->assertFileExists($path);

        $newPattern = new Pattern();
        $newPattern->loadModel($path);
        $prediction = $newPattern->predict('a');
        $this->assertEquals('b', $prediction);

        unlink($path);
    }
}
