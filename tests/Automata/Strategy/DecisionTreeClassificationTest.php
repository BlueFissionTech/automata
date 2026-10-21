<?php

namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Strategy\{DecisionTreeClassification, KNearestPrediction};
use BlueFission\Arr;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/** Fitted trees must retain typed labels, isolated fits and usable saved parameters. */
final class DecisionTreeClassificationTest extends TestCase
{
    public function testFitsNonlinearClassesAndPreservesTypedLabels(): void
    {
        $tree = new DecisionTreeClassification(4);
        $tree->train([[0.0], [1.0], [4.0], [5.0], [8.0], [9.0]], [1, 1, '1', '1', false, false], 0.0);
        self::assertSame(1, $tree->predict([0.2]));
        self::assertSame('1', $tree->predict([4.2]));
        self::assertFalse($tree->predict([8.2]));
        self::assertSame(0.0, $tree->accuracy());
    }

    public function testCategoricalFeaturesAreNotCoercedIntoNumericValues(): void
    {
        $tree = new DecisionTreeClassification();
        $tree->train([['01'], ['01'], ['1'], ['1']], ['first', 'first', 'second', 'second'], 0.0);
        self::assertSame('first', $tree->predict(['01']));
        self::assertSame('second', $tree->predict(['1']));
        $this->expectException(InvalidArgumentException::class);
        $tree->predict([1]);
    }

    public function testReplacementFitDoesNotAccumulateOldTrainingRows(): void
    {
        $tree = new DecisionTreeClassification();
        $tree->train([[0], [0], [0]], ['old', 'old', 'old'], 0.0);
        $tree->train([[0]], ['new'], 0.0);
        self::assertSame('new', $tree->predict([0]));
        try { $tree->train([[INF]], ['bad'], 0.0); self::fail('Nonfinite sample accepted.'); }
        catch (InvalidArgumentException) { self::assertSame('new', $tree->predict([0])); }
    }

    public function testMultipleFeatureColumnsKeepTheirSplitSemantics(): void
    {
        $tree = new DecisionTreeClassification(4);
        $tree->train([[0, 'red'], [1, 'red'], [0, 'blue'], [1, 'blue']],
            ['r0', 'r1', 'b0', 'b1'], 0.0);
        self::assertSame('r0', $tree->predict([0, 'red']));
        self::assertSame('r1', $tree->predict([1, 'red']));
        self::assertSame('b0', $tree->predict([0, 'blue']));
        self::assertSame('b1', $tree->predict([1, 'blue']));
    }

    public function testNumericBoundariesUseTrainingGapsWithoutReadingHoldout(): void
    {
        $tree = new DecisionTreeClassification(3);
        $tree->train([[0.0], [1.0], [8.0], [9.0], [-8.0], [-9.0]],
            ['wait', 'wait', 'assist', 'assist', 'decline', 'decline'], 0.0);
        self::assertSame('decline', $tree->predict([-7.8]));
        self::assertSame('wait', $tree->predict([0.2]));
        self::assertSame('assist', $tree->predict([8.2]));
        // Finite extreme values must not create an infinite midpoint.
        $tree->train([[1.0e308], [1.2e308], [1.6e308], [1.7e308]], ['a', 'a', 'b', 'b'], 0.0);
        self::assertSame('a', $tree->predict([1.1e308]));
        self::assertSame('b', $tree->predict([1.65e308]));
    }

    public function testDepthLimitConstrainsFittingAndIndependentHoldoutIsAvailable(): void
    {
        $samples = [[0], [1], [4], [5], [8], [9]];
        $labels = ['a', 'a', 'b', 'b', 'c', 'c'];
        $shallow = new DecisionTreeClassification(1);
        $shallow->train($samples, $labels, 0.0);
        $predicted = Arr::make($samples)->map($shallow->predict(...))->val();
        self::assertNotSame($labels, $predicted);
        $tree = new DecisionTreeClassification(4);
        $tree->train([...$samples, [0.2], [4.2], [8.2]], [...$labels, 'a', 'b', 'c'], 1 / 3);
        self::assertSame(1.0, $tree->accuracy());
        self::assertSame(4, $tree->maximumDepth());
    }

    public function testSavedFittedParametersRestoreAndWrongArtifactLeavesModelUnchanged(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'automata-tree-');
        try {
            $tree = new DecisionTreeClassification(3);
            $tree->train([[0], [1], [8], [9]], [true, true, 'urgent', 'urgent'], 0.0);
            self::assertTrue($tree->saveModel($path));
            $restored = new DecisionTreeClassification(1);
            self::assertTrue($restored->loadModel($path));
            self::assertSame(3, $restored->maximumDepth());
            self::assertTrue($restored->predict([0.2]));
            self::assertSame('urgent', $restored->predict([8.2]));
            $other = new KNearestPrediction(1);
            $other->train([[0]], ['other'], 0.0);
            self::assertTrue($other->saveModel($path));
            self::assertFalse($restored->loadModel($path));
            self::assertSame('urgent', $restored->predict([8.2]));
        } finally { if (is_file($path)) { unlink($path); } }
    }

    public function testInvalidShapeSplitOrLabelsCannotReplaceFittedState(): void
    {
        $tree = new DecisionTreeClassification();
        foreach ([[[], [], 0.0], [[[0], [1, 2]], ['a', 'b'], 0.0],
            [[[0], ['mixed']], ['a', 'b'], 0.0], [[[0]], [null], 0.0],
            [[[0]], ['a'], NAN], [[[0]], ['a'], 1.0], [[[0]], ['a'], -0.1]] as [$rows, $labels, $fraction]) {
            try { $tree->train($rows, $labels, $fraction); self::fail('Invalid fit accepted.'); }
            catch (InvalidArgumentException) { self::addToAssertionCount(1); }
        }
        $this->expectException(LogicException::class);
        $tree->predict([0]);
    }
}
