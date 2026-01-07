<?php
namespace BlueFission\Tests\Bot\NaturalLanguage;

use BlueFission\Bot\NaturalLanguage\Documenter;
use BlueFission\Bot\NaturalLanguage\Tokenizer;
use PHPUnit\Framework\TestCase;

class DocumenterTest extends TestCase {
 	static $classname = 'BlueFission\Bot\NaturalLanguage\Documenter';

	protected function setUp(): void
	{
		$this->object = new static::$classname();
		$this->commands = array();
	}

	public function testDocumenterExpectsToken()
	{
		$this->expectException(\Exception::class);

		$this->commands[] = "TYPE Person EXPECTS {'name'}";
		$tokens = Tokenizer::parse($this->commands);

		// die(var_dump($tokens));
		foreach ( $tokens as $token )
			$this->object->push($token);
		return $this->object->getTree();
	}
}
