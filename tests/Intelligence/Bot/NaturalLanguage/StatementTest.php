<?php 
namespace BlueFission\Tests\Bot\NaturalLanguage\Statement;

class StatementTest extends \PHPUnit_Framework_TestCase {
 	static $classname = 'BlueFission\Bot\NaturalLanguage\Statement';

	public function setup()
	{
		$this->object = new static::$classname();
	}

	public function testStatementPromptsToSatisfy()
	{
		echo $this->object->satisfy();
		// die();
	}

	public function testStatementGeneratesStatement()
	{

	}

	public function testStatementExpectsToken()
	{

	}

}