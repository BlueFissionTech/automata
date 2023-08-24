<?php 
namespace BlueFission\Tests\Bot\NaturalLanguage;

use BlueFission\Bot\NaturalLanguage\Documenter;
use BlueFission\Bot\NaturalLanguage\Tokenizer;

class DocumenterTest extends \PHPUnit_Framework_TestCase {
 	static $classname = 'BlueFission\Bot\NaturalLanguage\Documenter';

	public function setup()
	{
		$this->object = new static::$classname();
		$this->commands = array();
	}

	/** 
 	 * @expectedException Exception
 	 */
	public function testDocumenterExpectsToken()
	{
		$this->commands[] = "TYPE Person EXPECTS {'name'}";
		$tokens = Tokenizer::parse($this->commands);

		// die(var_dump($tokens));
		foreach ( $tokens as $token )
			$this->object->push($token);
		return $this->object->getTree();
	}
}