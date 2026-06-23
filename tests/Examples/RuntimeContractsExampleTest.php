<?php

namespace BlueFission\Tests\Examples;

use PHPUnit\Framework\TestCase;

class RuntimeContractsExampleTest extends TestCase
{
    public function testRuntimeContractsExampleEmitsCurrentContractPayload(): void
    {
        $output = $this->runExample('examples/generic/runtime_contracts.php');
        $payload = json_decode($output, true);

        $this->assertIsArray($payload);
        $this->assertSame('request.context requires step_up_review', $payload['statement_bundle']['name']);
        $this->assertSame('needs', $payload['statement_bundle']['relationship']);
        $this->assertSame('corrected', $payload['feedback']['review']['status']);
        $this->assertSame('training_signal', $payload['feedback']['training_signal']['status']);
        $this->assertSame('restrict', $payload['herd']['decision']);
        $this->assertSame('operational', $payload['lane_pressure']['dominant_lane']);
        $this->assertNotEmpty($payload['capability_vocabulary']);
    }

    public function testFeedbackLoopExampleRunsWithoutMissingOptionWarnings(): void
    {
        $output = $this->runExample('examples/generic/disaster_response/initiative_feedback_loop.php');

        $this->assertStringNotContainsString('Warning', $output);
        $this->assertStringContainsString('Feedback score:', $output);
    }

    public function testAnomalyGatewayExampleHidesVendorDeprecationNoise(): void
    {
        $output = $this->runExample('examples/anomaly_gateway_basic.php');

        $this->assertStringNotContainsString('Deprecated', $output);
        $this->assertStringContainsString('Anomalies flagged:', $output);
    }

    private function runExample(string $path): string
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path) . ' 2>&1';
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, "{$path} should exit cleanly.\n" . implode("\n", $output));

        return implode("\n", $output);
    }
}
