<?php

namespace BlueFission\Tests\Automata\Feature;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Feature\PolynomialFeatures;
use BlueFission\Tests\Automata\Support\RecordingStructureFactory;

class PolynomialFeaturesTest extends TestCase
{
    public function testGeneratesPolynomialAndInteractionTerms(): void
    {
        $features = new PolynomialFeatures(2);

        $data = [
            [1, 2],
        ];

        $result = $features->transform($data);

        $this->assertCount(1, $result->val());

        $row = $result->get(0);
        $this->assertSame(1, $row->get(0)); // x^1
        $this->assertSame(1, $row->get(1)); // x^2
    }

    public function testUsesInjectedStructureFactory(): void
    {
        $factory = new RecordingStructureFactory();
        $features = new PolynomialFeatures(2, $factory);

        $result = $features->transform([[2, 3]]);

        $this->assertSame(4, $result->get(0)->get(1));
        $this->assertGreaterThanOrEqual(3, $factory->vecCalls);
    }
}
