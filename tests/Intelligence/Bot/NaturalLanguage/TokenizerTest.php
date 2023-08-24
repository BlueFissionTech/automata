<?php 
namespace BlueFission\Tests\Bot\NaturalLanguage;

use BlueFission\Bot\NaturalLanguage\Tokenizer;
// use BlueFission\Bot\NaturalLanguage\Grammar;

class TokenizerTest extends \PHPUnit_Framework_TestCase {
 	static $classname = 'BlueFission\Bot\NaturalLanguage\Tokenizer';

	public function setup()
	{
		// $this->object = new static::$classname();
		$this->commands = array();
	}

	public function testTokenizerRecognizesTokens()
	{
		$expected = array(
			array(
				'match'=>'&',
				'token'=>'T_TYPE_INDICATOR',
				'line'=>1
			),
			array(
				'match'=>' ',
				'token'=>'T_WHITESPACE',
				'line'=>1
			),
			array(
				'match'=>'TYPE',
				'token'=>'T_SYMBOL',
				'line'=>1
			),
			array(
				'match'=>' ',
				'token'=>'T_WHITESPACE',
				'line'=>1
			),
			array(
				'match'=>'Person',
				'token'=>'T_SYMBOL',
				'line'=>1
			),
			array(
				'match'=>' ',
				'token'=>'T_WHITESPACE',
				'line'=>1
			),
			array(
				'match'=>'EXPECTS',
				'token'=>'T_SYMBOL',
				'line'=>1
			),
			array(
				'match'=>' ',
				'token'=>'T_WHITESPACE',
				'line'=>1
			),
			array(
				'match'=>'{',
				'token'=>'T_CLASS_OPEN_BRACKET',
				'line'=>1
			),
			array(
				'match'=>'\'',
				'token'=>'T_SINGLE_QUOTE',
				'line'=>1
			),
			array(
				'match'=>'name',
				'token'=>'T_SYMBOL',
				'line'=>1
			),
			array(
				'match'=>'\'',
				'token'=>'T_SINGLE_QUOTE',
				'line'=>1
			),
			array(
				'match'=>'}',
				'token'=>'T_CLASS_CLOSE_BRACKET',
				'line'=>1
			),
			array(
				'match'=>'_EOL_',
				'token'=>'T_EOL',
				'line'=>1
			),
		);
		$this->commands[] = "& TYPE Person EXPECTS {'name'}";
		$tokens = Tokenizer::parse($this->commands);

		$this->assertEquals($expected, $tokens);
	}
}