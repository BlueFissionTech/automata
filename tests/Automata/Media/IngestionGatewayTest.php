<?php

namespace BlueFission\Tests\Automata\Media;

use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\Ingestion\Gateway as IngestionGateway;
use BlueFission\Automata\Media\Ingestion\TextIngestor;
use PHPUnit\Framework\TestCase;

class IngestionGatewayTest extends TestCase
{
    public function testTextIngestionFromString(): void
    {
        $gateway = new IngestionGateway();
        $gateway->registerIngestor(new TextIngestor(), 'text', ['types' => [InputType::TEXT]]);

        $item = $gateway->ingest('Hello world');

        $this->assertSame(InputType::TEXT, $item->type());
        $this->assertSame('Hello world', $item->content());
    }

    public function testTextIngestorSupportsJsonMimeWithoutRawMembershipCheck(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'automata-json-');
        file_put_contents($path, '{"status":"ok"}');

        try {
            $ingestor = new TextIngestor();

            $this->assertTrue($ingestor->supports($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
