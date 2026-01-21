<?php

namespace BlueFission\Tests\Examples\DisasterResponse;

use PHPUnit\Framework\TestCase;

class DatasetExecutionExampleTest extends TestCase
{
    public function testDatasetExecutionRunsWithMock(): void
    {
        $cmd = 'php examples/generic/disaster_response/dataset_execution.php --use-mock --limit=5';
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, 'Dataset execution should exit successfully');

        $json = implode("\n", $output);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertSame('mock', $data['status'] ?? null);
        $count = $data['count'] ?? null;
        $this->assertIsInt($count);
        $this->assertGreaterThan(0, $count);
        $this->assertLessThanOrEqual(5, $count);
        $this->assertArrayHasKey('feature_normalization', $data);
    }
}
