<?php
namespace BlueFission\Automata\Analysis;

// IIntentAnalyzer.php
use BlueFission\Automata\Context;
use BlueFission\Arr;

interface IAnalyzer
{
    public function analyze(string $input, Context $context, array $keywords): Arr;
}
