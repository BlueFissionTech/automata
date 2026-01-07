<?php

namespace BlueFission\Tests\Automata\Capstone;

use PHPUnit\Framework\TestCase;

class CoordinationPipelineExampleTest extends TestCase
{
    public function testCoordinationPipelineProducesSummaryAndNarrative(): void
    {
        $cmd = 'php examples/disaster_response/coordination_pipeline/run.php';
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, 'Coordination pipeline script should exit successfully');

        $fullOutput = implode("\n", $output);

        // The script prints JSON, then a separator line, then a Markdown narrative.
        [$jsonPart] = explode('---', $fullOutput, 2);

        $data = json_decode(trim($jsonPart), true);
        $this->assertIsArray($data, 'First part of output should be valid JSON');

        $this->assertArrayHasKey('events', $data);
        $this->assertArrayHasKey('predictions', $data);

        $this->assertNotEmpty($data['events'], 'Events should not be empty');
        $this->assertNotEmpty($data['predictions'], 'Predictions should not be empty');
    }
}

