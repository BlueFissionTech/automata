<?php

namespace BlueFission\Tests\Automata\Intent;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Intent\Intent;

class IntentTest extends TestCase
{
    public function testIntentStoresLabelNameAndCriteria(): void
    {
        $criteria = [
            'keywords' => [
                ['word' => 'truck', 'priority' => 1],
            ],
        ];

        $intent = new Intent('dispatch_truck', 'Dispatch Truck', $criteria);

        $this->assertSame('dispatch_truck', $intent->getLabel());
        $this->assertSame('Dispatch Truck', $intent->getName());
        $this->assertSame($criteria, $intent->getCriteria());
        $this->assertSame([], $intent->getRelatedIntents());
    }

    public function testAddCriteriaAppendsToExistingBucket(): void
    {
        $intent = new Intent('dispatch', 'Dispatch', ['keywords' => []]);

        $intent->addCriteria('keywords', ['word' => 'road', 'priority' => 1]);
        $intent->addCriteria('keywords', ['word' => 'bridge', 'priority' => 2]);

        $criteria = $intent->getCriteria();

        $this->assertArrayHasKey('keywords', $criteria);
        $this->assertCount(2, $criteria['keywords']);
        $this->assertSame('road', $criteria['keywords'][0]['word']);
        $this->assertSame('bridge', $criteria['keywords'][1]['word']);
    }
}

