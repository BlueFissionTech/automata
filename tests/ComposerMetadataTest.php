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

    public function testOptionalProviderClientsDoNotBlockRuntimePackageInstall(): void
    {
        $composer = $this->composer();
        $require = $composer['require'];
        $requireDev = $composer['require-dev'];
        $suggest = $composer['suggest'];

        $this->assertArrayNotHasKey('bluefission/simpleclients', $require);
        $this->assertArrayNotHasKey('google-gemini-php/client', $require);
        $this->assertArrayNotHasKey('nyholm/psr7', $require);
        $this->assertArrayNotHasKey('orhanerday/open-ai', $require);
        $this->assertArrayNotHasKey('symfony/http-client', $require);

        $this->assertSame('dev-master', $requireDev['bluefission/simpleclients']);
        $this->assertArrayHasKey('bluefission/simpleclients', $suggest);
        $this->assertArrayHasKey('google-gemini-php/client', $suggest);
        $this->assertArrayHasKey('nyholm/psr7', $suggest);
        $this->assertArrayHasKey('orhanerday/open-ai', $suggest);
        $this->assertArrayHasKey('symfony/http-client', $suggest);
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

    public function testPackagistWorkflowUsesRegisteredPackageUpdateEndpoint(): void
    {
        $workflow = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'packagist.yaml');

        $this->assertIsString($workflow);
        $this->assertStringContainsString('PACKAGIST_PACKAGE_URL: https://packagist.org/packages/bluefission/automata', $workflow);
        $this->assertStringContainsString('PACKAGIST_REPOSITORY: https://github.com/BlueFissionTech/automata', $workflow);
        $this->assertStringContainsString('api/update-package?username=${PACKAGIST_USERNAME}&apiToken=${PACKAGIST_TOKEN}', $workflow);
        $this->assertStringContainsString('repository', $workflow);
        $this->assertStringContainsString('url', $workflow);
        $this->assertStringContainsString('${repository}', $workflow);
        $this->assertStringContainsString('--user-agent "BlueFissionTech/automata Packagist workflow"', $workflow);
        $this->assertStringContainsString('Retrying Packagist update using the repository URL.', $workflow);
    }
}
