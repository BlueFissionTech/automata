<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Automata\Learning\{LearningBundle, BundleSnapshot};
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Manifest dependencies bind exact reusable artifacts, not a fixed workflow shape. */
final class LearningBundleTest extends TestCase
{
    /** A small descriptor fixture keeps artifact verification independent of model code. */
    private function component(string $slot, string $bytes, array $requires = []): array
    {
        return ['slot' => $slot, 'kind' => $slot, 'id' => $slot, 'version' => '1',
            'artifact' => ['key' => $slot, 'format' => 'fixture-text.v1', 'sha256' => hash('sha256', $bytes)], 'requires' => $requires];
    }

    public function testRoundTripResolvesExactBytesAndDetachesManifest(): void
    {
        $model = $this->component('model', 'fitted-parameters');
        $workflow = $this->component('workflow', 'graph', [['slot' => 'model', 'id' => 'model', 'version' => '1', 'sha256' => $model['artifact']['sha256']]]);
        $bundle = new LearningBundle('agent', '1', 'tenant-a', [$workflow, $model]);
        $restored = LearningBundle::fromArray($bundle->toArray());
        self::assertSame($bundle->digest(), $restored->digest());
        $bytes = ['model' => 'fitted-parameters', 'workflow' => 'graph'];
        $snapshot = BundleSnapshot::resolve($restored, static fn (array $descriptor) => $bytes[$descriptor['artifact']['key']]);
        $bytes['model'] = 'changed-later';
        self::assertSame('fitted-parameters', $snapshot->artifact('model'));
        self::assertSame('tenant-a', $snapshot->manifest()->scope());
        $copy = $bundle->toArray(); $copy['components'][0]['version'] = '9';
        self::assertSame($restored->digest(), $bundle->digest());
    }

    public function testRejectsMixedVersionsMissingDependenciesAndDuplicateSlots(): void
    {
        $model = $this->component('model', 'fitted');
        foreach ([['slot' => 'missing', 'id' => 'model', 'version' => '1', 'sha256' => $model['artifact']['sha256']],
            ['slot' => 'model', 'id' => 'model', 'version' => '2', 'sha256' => $model['artifact']['sha256']],
            ['slot' => 'model', 'id' => 'model', 'version' => '1', 'sha256' => hash('sha256', 'other')]] as $dependency) {
            try { new LearningBundle('agent', '1', 'scope', [$this->component('workflow', 'graph', [$dependency]), $model]); self::fail('Incompatible dependency accepted.'); }
            catch (InvalidArgumentException) { self::addToAssertionCount(1); }
        }
        $this->expectException(InvalidArgumentException::class);
        new LearningBundle('agent', '1', 'scope', [$model, $model]);
    }

    public function testRejectsChangedArtifactAndUnsupportedRecordWithoutExecutingIt(): void
    {
        $bundle = new LearningBundle('agent', '1', 'scope', [$this->component('model', 'fitted')]);
        try { BundleSnapshot::resolve($bundle, static fn () => 'corrupt'); self::fail('Changed artifact accepted.'); }
        catch (InvalidArgumentException) { self::addToAssertionCount(1); }
        $record = $bundle->toArray(); $record['schema_version'] = 2;
        $this->expectException(InvalidArgumentException::class);
        LearningBundle::fromArray($record);
    }

    /** Resolver output must be raw bytes, never an implicitly converted value object. */
    public function testArtifactResolverCannotSubstituteAStringWrapper(): void
    {
        $bundle = new LearningBundle('agent', '1', 'scope', [$this->component('model', 'fitted')]);
        $this->expectException(InvalidArgumentException::class);
        BundleSnapshot::resolve($bundle, static fn () => \BlueFission\Str::make('fitted'));
    }
}
