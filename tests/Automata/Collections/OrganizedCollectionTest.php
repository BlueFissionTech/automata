<?php
namespace BlueFission\Tests\Automata\Collections;

use BlueFission\Automata\Collections\OrganizedCollection;
use PHPUnit\Framework\TestCase;

class OrganizedCollectionTest extends TestCase
{
    private $collection;

    protected function setUp(): void
    {
        $this->collection = new OrganizedCollection();
    }

    public function testAddAndRetrieve()
    {
        $this->collection->add('value1', 'key1', 1);
        $value = $this->collection->get('key1');
        $this->assertEquals('value1', $value);
    }

    public function testSort()
    {
        $this->collection->add('value1', 'key1', 1);
        $this->collection->add('value2', 'key2', 2);
        $this->collection->sort();

        $values = [];
        foreach ($this->collection as $key => $value) {
            $values[] = $value['value'];
        }
        $this->assertEquals(['value2', 'value1'], $values);
    }

    public function testOptimize()
    {
        $this->collection->add('value1', 'key1', 1);
        $this->collection->add('value2', 'key2', 2);
        $this->collection->setDecay(true, 0.001);
        $this->collection->optimize(1.5);

        $this->assertNotNull($this->collection->get('key2'));
        $this->assertNull($this->collection->get('key1'));
    }

    public function testStats()
    {
        $this->collection->add('value1', 'key1', 1);
        $this->collection->add('value2', 'key2', 2);
        $stats = $this->collection->stats();
        $this->assertArrayHasKey('mean1', $stats);
    }

    public function testCollectionMathIsAccurate()
	{
		$tokens = ['A', 'B', 'C', 'D', 'E'];
		$number = [600, 470, 170, 430, 300];

		$total = array_sum($number);

		$values = [];
		
		while ( count($values) < $total ) {
			foreach ($tokens as $key=>$value) {
				if ($number[$key] > 0 ) {
					$number[$key]--;
					$values[] = $value;
					$this->collection->add($value, $value);
				}
			}
		}

		$this->collection->sort();
		$this->collection->stats();
		$data = $this->collection->data();

		$this->assertEquals(5, $data['count']);
		$this->assertEquals(1970, $data['total']);
		$this->assertEquals(600, $data['max']);
		$this->assertEquals(170, $data['min']);
		$this->assertEquals(394, $data['mean1']);
		$this->assertEquals(21704, (int)$data['popvariance1']);
		$this->assertEquals(147, (int)$data['popstd1']);
		$this->assertEquals(27130, (int)$data['variance1']);
		$this->assertEquals(164, (int)$data['std1']);

	}
}
