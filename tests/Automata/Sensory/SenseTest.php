<?php
namespace BlueFission\Tests\Automata\Sensory;

use BlueFission\Automata\Sensory\Sense;
use BlueFission\Behavioral\Behaviors\Event;
use PHPUnit\Framework\TestCase;

class SenseTest extends TestCase
{
    public function testAttentionScoreClampsConsumedFractionWithoutChangingUnits(): void
    {
        $sense = new Sense();
        $config = new \ReflectionProperty(Sense::class, '_config');
        $settings = new \ReflectionProperty(Sense::class, '_settings');
        $setAttention = static function (int $initial, int $remaining) use ($sense, $config, $settings): void {
            $configured = $config->getValue($sense);
            $configured['attention'] = $initial;
            $config->setValue($sense, $configured);
            $current = $settings->getValue($sense);
            $current['attention'] = $remaining;
            $settings->setValue($sense, $current);
        };

        $setAttention(100, 75);
        $this->assertSame(0.25, $sense->attentionScore());
        $setAttention(100, 125);
        $this->assertSame(0.0, $sense->attentionScore());
        $setAttention(100, -25);
        $this->assertSame(1.0, $sense->attentionScore());
        $setAttention(0, 0);
        $this->assertSame(0.0, $sense->attentionScore());
    }

    public function testInvokeDispatchesSuccessAndCompleteEvents(): void
    {
        $sense = new Sense();

        $captured = [];
        $sense->behavior(new Event(Event::SUCCESS), function ($behavior) use (&$captured) {
            $captured['success'] = $behavior->context;
        });
        $sense->behavior(new Event(Event::COMPLETE), function ($behavior) use (&$captured) {
            $captured['complete'] = $behavior->context;
        });

        $sense->invoke('test sensory input');

        $this->assertArrayHasKey('success', $captured);
        $this->assertArrayHasKey('complete', $captured);
        $this->assertIsArray($captured['complete']);
        $this->assertArrayHasKey('variance1', $captured['complete']);
    }
}
