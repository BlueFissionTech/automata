<?php

namespace BlueFission\Tests\Examples;

use PHPUnit\Framework\TestCase;

class VibeProfileFillInExampleTest extends TestCase
{
    public function testVibeProfileFillInExampleRuns(): void
    {
        $cmd = 'php examples/generic/vibe_profile_fillin.php';
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, 'Example should exit cleanly.');

        $json = json_decode(implode("\n", $output), true);

        $this->assertIsArray($json);
        $this->assertSame('Draft intro: default-draft', $json['file_profile_output'] ?? null);
        $this->assertStringStartsWith("You are an editorial book agent.\nFavor concise, publication-ready prose.\n\nDraft intro: ", $json['file_profile_prompt'] ?? '');
        $this->assertSame('Draft intro: editorial-draft', $json['named_profile_output'] ?? null);
        $this->assertStringStartsWith('You are the fast editorial profile. Prefer sharp structure and clean copy.', $json['named_profile_prompt'] ?? '');
    }
}
