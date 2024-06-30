<?php
namespace BlueFission\Automata\Analysis;

// IIntentAnalyzer.php
use BlueFission\Automata\Analysis\Context;

interface IAnalyzer
{
    public function analyze(string $input, Context $context, array $keywords): array;
}
