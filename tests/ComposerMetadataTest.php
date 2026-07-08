<?php

declare(strict_types=1);

namespace BlueFission\Tests;

use PHPUnit\Framework\TestCase;

final class ComposerMetadataTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        $json = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'composer.json');

        $this->assertIsString($json);

        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);

        return $decoded;
    }

    public function testPackageMetadataIsReadyForPackagist(): void
    {
        $composer = $this->composer();

        $this->assertSame('bluefission/automata', $composer['name']);
        $this->assertSame('library', $composer['type']);
        $this->assertSame('https://github.com/BlueFissionTech/automata', $composer['homepage']);
        $this->assertSame('https://github.com/BlueFissionTech/automata/issues', $composer['support']['issues']);
        $this->assertSame('https://github.com/BlueFissionTech/automata', $composer['support']['source']);
    }

    public function testChroniclerUsesAlphaReleaseConstraint(): void
    {
        $composer = $this->composer();

        $this->assertSame('^0.1.2-alpha', $composer['require']['bluefission/chronicler']);
        $this->assertStringContainsString('-alpha', $composer['require']['bluefission/chronicler']);
    }

    public function testPackageArchiveExcludesHarnessAndDevelopmentFiles(): void
    {
        $composer = $this->composer();
        $exclude = $composer['archive']['exclude'] ?? [];

        $this->assertContains('/.github', $exclude);
        $this->assertContains('/AGENTS.md', $exclude);
        $this->assertContains('/composer.lock', $exclude);
        $this->assertContains('/tests', $exclude);
        $this->assertContains('/scripts', $exclude);
    }
}
