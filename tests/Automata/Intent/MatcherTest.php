<?php

namespace BlueFission\Tests\Automata\Intent;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Intent\Matcher;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use BlueFission\Automata\Intent\Skill\ISkill;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Arr;

class DummyAnalyzer implements IAnalyzer
{
    /**
     * Very simple analyzer: sum keyword weights when the word appears in the input.
     *
     * @param array<string,array<int,array{weight:float,text:string}>> $keywords
     */
    public function analyze(string $input, Context $context, array $keywords): Arr
    {
        $scores = [];
        $lower  = strtolower($input);

        foreach ($keywords as $label => $phrases) {
            $score = 0.0;
            foreach ($phrases as $phrase) {
                if (str_contains($lower, strtolower($phrase['text']))) {
                    $score += (float)$phrase['weight'];
                }
            }
            $scores[$label] = $score;
        }

        arsort($scores);

        return Arr::make($scores);
    }
}

class TestSkill extends BaseSkill
{
    private string $lastResponse = '';

    public function execute(Context $context)
    {
        $this->lastResponse = (string)$context->get('message', '');
    }

    public function response(): string
    {
        return $this->lastResponse;
    }
}

class MatcherTest extends TestCase
{
    public function testMatcherRegistersIntentsSkillsAndProcesses(): void
    {
        $analyzer = new DummyAnalyzer();
        $matcher  = new Matcher($analyzer);

        $truckIntent = new Intent('dispatch_truck', 'Dispatch Truck', [
            'keywords' => [
                ['word' => 'truck', 'priority' => 2],
                ['word' => 'road',  'priority' => 1],
            ],
        ]);

        $airliftIntent = new Intent('dispatch_airlift', 'Dispatch Airlift', [
            'keywords' => [
                ['word' => 'airlift',   'priority' => 2],
                ['word' => 'helicopter','priority' => 1],
            ],
        ]);

        $truckSkill = new TestSkill('truck_skill');
        $airSkill   = new TestSkill('airlift_skill');

        $matcher
            ->registerIntent($truckIntent)
            ->registerIntent($airliftIntent)
            ->registerSkill($truckSkill)
            ->registerSkill($airSkill)
            ->associate($truckIntent, $truckSkill)
            ->associate($airliftIntent, $airSkill);

        $context = new Context();
        $context->set('message', 'Requesting helicopter airlift to Hospital A');

        // Verify match scores favour the airlift intent.
        $scores = $matcher->match('helicopter airlift to Hospital A', $context);
        $this->assertInstanceOf(Arr::class, $scores);

        $scoresArray = $scores->val();
        $this->assertGreaterThan(
            $scoresArray['dispatch_truck'] ?? 0,
            $scoresArray['dispatch_airlift'] ?? 0
        );

        // Verify process selects the correct skill and returns its response.
        $response = $matcher->process($airliftIntent, $context);

        $this->assertSame('Requesting helicopter airlift to Hospital A', $response);
    }
}

