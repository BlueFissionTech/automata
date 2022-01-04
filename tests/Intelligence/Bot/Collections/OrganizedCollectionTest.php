<?php
namespace BlueFission\Tests\Bot\Collections;

use BlueFission\Bot\Collections\OrganizedCollection;
 
class OrganizedCollectionTest extends \PHPUnit_Framework_TestCase {

 	static $classname = 'BlueFission\Bot\Collections\OrganizedCollection';

	public function setup()
	{
		$this->object = new static::$classname();
	}

	public function testCollectionMathIsAccurate()
	{
		$tokens = array('A', 'B', 'C', 'D', 'E');
		$number = array(600, 470, 170, 430, 300);

		$total = array_sum($number);

		$values = array();
		// echo $total;
		// $this->object->setMax(2000);
		
		while ( count($values) < $total ) {
		// while ( count($values) < 1971 ) {
			foreach ($tokens as $key=>$value) {
				// echo $value.PHP_EOL;
				if ($number[$key] > 0 ) {
					$number[$key]--;
					$values[] = $value;
					$this->object->add($value, $value);
				}
			}
		}

		$this->object->sort();
		$this->object->stats();
		$data = $this->object->data();

		// $data['values'] = null;
		// die(var_dump($data));

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