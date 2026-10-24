<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Str;
use InvalidArgumentException;

/** Verified bytes captured together; runtime loaders must not reread mutable paths. */
final class BundleSnapshot
{
    /** Construction is restricted to complete, verified resolution. */
    private function __construct(private readonly LearningBundle $bundle, private readonly array $artifacts) {}

    /**
     * Resolve through trusted host storage and verify every artifact before return.
     * The resolver receives a component descriptor, never permission to execute it.
     * Formats and serialized-model safety remain the host loader's responsibility.
     */
    public static function resolve(LearningBundle $bundle, callable $readArtifact): self
    {
        $artifacts = [];
        foreach ($bundle->components() as $component) {
            $bytes = $readArtifact($component);
            // Primitive predicates can unwrap value objects; artifact integrity
            // requires literal bytes without implicit conversion callbacks.
            if (is_object($bytes) || !Str::is($bytes) || !hash_equals($component['artifact']['sha256'], hash('sha256', $bytes))) {
                throw new InvalidArgumentException('Bundle artifact is missing or its bytes changed.');
            }
            $artifacts[$component['slot']] = $bytes;
        }
        return new self($bundle, $artifacts);
    }

    /** The immutable manifest identifies the complete compatible set. */
    public function manifest(): LearningBundle { return $this->bundle; }
    /** Return the already verified bytes; no filesystem lookup or model fit occurs. */
    public function artifact(string $slot): string
    {
        return $this->artifacts[$slot] ?? throw new InvalidArgumentException('Unknown bundle slot.');
    }
}
