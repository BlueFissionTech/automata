<?php

namespace BlueFission\Tests\Automata\Encoding;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Encoding\CategoricalEncoder;

class CategoricalEncoderTest extends TestCase
{
    public function testOrdinalEncodingWithDefaultCategory(): void
    {
        $encoder = new CategoricalEncoder(false, 'UNKNOWN');
        $data    = ['road', 'bridge', 'road', 'hospital'];

        $encoder->fit($data);

        $encoded = $encoder->transform(['road', 'hospital', 'airstrip']);

        $this->assertCount(3, $encoded);
        $this->assertIsInt($encoded[0]);
        $this->assertIsInt($encoded[1]);
        $this->assertIsInt($encoded[2]);
        $this->assertSame($encoded[2], $encoded[1], 'Unseen category should map to default index');
    }

    public function testOneHotEncoding(): void
    {
        $encoder = new CategoricalEncoder(true);
        $data    = ['A', 'B', 'C'];

        $encoder->fit($data);

        $encoded = $encoder->transform(['A', 'C']);

        $this->assertCount(2, $encoded);
        $this->assertSame(1, $encoded[0]->get(0));
        $this->assertSame(0, $encoded[0]->get(1));
        $this->assertSame(1, $encoded[1]->get(2));
    }
}

