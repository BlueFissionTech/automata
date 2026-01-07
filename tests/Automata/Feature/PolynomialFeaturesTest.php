<?php

namespace BlueFission\Tests\Automata\Feature;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Feature\PolynomialFeatures;

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
}
