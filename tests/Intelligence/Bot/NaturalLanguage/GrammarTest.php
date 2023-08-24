<?php 
// http://www.english-for-students.com/Frequently-Used-Sentences.html
// http://www.talkenglish.com/speaking/regular/greetings1.aspx

namespace BlueFission\Tests\Bot\NaturalLanguage\Grammar;

use BlueFission\Bot\NaturalLanguage\Grammar;

class GrammarTest extends \PHPUnit_Framework_TestCase {
 	static $classname = 'BlueFission\Bot\NaturalLanguage\Grammar';

	public function setup()
	{
		$this->object = new static::$classname();
	}

	public function testGrammarLearnsWords()
	{
		$question = "What do I have to do today?";

		// add to word bank.
		// add to grammar bank.
		// add to concept bank.
		// queue up commands.
		// begin automatic behaviors

		$bank = array();
	}

	public function testGrammarLearnsRules()
	{
		
	}
	public function testGrammarLearnsPatterns()
	{
	
	}
}