<?php

namespace BlueFission\Tests\Automata;

use BlueFission\Automata\Behavioral\Trainable;
use BlueFission\Automata\Behavioral\TrainableMethod;
use BlueFission\Automata\Learning\MemoryTrainingRegistry;
use BlueFission\Automata\Learning\TrainingObservation;
use BlueFission\Automata\Learning\TrainingStrategyMatrix;
use BlueFission\Automata\Learning\ExactLookupStrategy;
use BlueFission\Automata\GameTheory\Player;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use PHPUnit\Framework\TestCase;

class TrainableTest extends TestCase
{
    public function testObservationDoesNotBypassByDefaultAndRecordsUtcMetadata(): void
    {
        $worker = new TrainableWorker();
        $worker->registerTrainableMethod('lookup');

        self::assertSame('answer:one', $worker->trainableInvoke('lookup', 'one'));
        self::assertSame('answer:one', $worker->trainableInvoke('lookup', 'one'));
        self::assertSame(2, $worker->calls);

        $records = $worker->trainingRegistry()->observations(TrainableWorker::class, 'lookup');
        self::assertCount(2, $records);
        self::assertInstanceOf(TrainingObservation::class, $records[0]);
        self::assertSame(['one'], $records[0]->input);
        self::assertSame('answer:one', $records[0]->output);
        self::assertSame([], $records[0]->context);
        self::assertMatchesRegularExpression('/Z$/', $records[0]->metadata['invoked_at_utc']);
    }

    public function testExactPredictionNeedsMatchingEvidenceAndPeriodicProbe(): void
    {
        $worker = new TrainableWorker();
        $worker->registerTrainableMethod('lookup', new TrainableMethod(
            minimumSamples: 2,
            minimumConfidence: 1.0,
            allowPrediction: true,
            minimumMatches: 2,
            probeEvery: 3,
        ));

        self::assertSame('answer:one', $worker->trainableInvoke('lookup', 'one'));
        self::assertSame('answer:one', $worker->trainableInvoke('lookup', 'one'));
        self::assertSame('answer:one', $worker->trainableInvoke('lookup', 'one'));
        self::assertSame(2, $worker->calls);
        self::assertSame('answer:one', $worker->trainableInvoke('lookup', 'one'));
        self::assertSame('answer:one', $worker->trainableInvoke('lookup', 'one'));
        self::assertSame(3, $worker->calls, 'The third eligible prediction must execute the source method.');
        self::assertSame('answer:two', $worker->trainableInvoke('lookup', 'two'));
        self::assertSame(4, $worker->calls, 'An unknown input must execute the source method.');
    }

    public function testTrackedStateIsAnExplicitPredictiveFeature(): void
    {
        $worker = new TrainableWorker();
        $worker->registerTrainableState('locale', fn (TrainableWorker $target) => $target->locale);
        $worker->registerTrainableMethod('lookup', new TrainableMethod(2, 1.0, true, 2));
        $worker->trainableInvoke('lookup', 'one');
        $worker->trainableInvoke('lookup', 'one');
        $worker->trainableInvoke('lookup', 'one');
        self::assertSame(2, $worker->calls);

        $worker->locale = 'fr';
        self::assertSame('answer:one', $worker->trainableInvoke('lookup', 'one'));
        self::assertSame(3, $worker->calls);
    }

    public function testConflictingOutputsDoNotBecomeExactPassThrough(): void
    {
        $worker = new TrainableWorker();
        $worker->registerTrainableMethod('changing', new TrainableMethod(2, 1.0, true, 2));

        self::assertSame(1, $worker->trainableInvoke('changing'));
        self::assertSame(2, $worker->trainableInvoke('changing'));
        self::assertSame(3, $worker->trainableInvoke('changing'));
    }

    public function testAttributeRegistrationStillRequiresExplicitInvocation(): void
    {
        $worker = new TrainableWorker();
        $worker->registerAttributedTrainableMethods();
        self::assertSame('tag:x', $worker->tag('x'));
        self::assertCount(0, $worker->trainingRegistry()->observations(TrainableWorker::class, 'tag'));
        self::assertSame('tag:x', $worker->trainableInvoke('tag', 'x'));
        self::assertCount(1, $worker->trainingRegistry()->observations(TrainableWorker::class, 'tag'));
    }

    public function testUnsupportedValuesAndMethodErrorsPreserveOrdinaryExecution(): void
    {
        $worker = new TrainableWorker();
        $registry = new MemoryTrainingRegistry();
        $worker->setTrainingRegistry($registry);
        $worker->registerTrainableMethod('identity');
        $value = new \stdClass();

        self::assertSame($value, $worker->trainableInvoke('identity', $value));
        self::assertCount(0, $registry->observations(TrainableWorker::class, 'identity'));
        $this->expectException(\RuntimeException::class);
        $worker->registerTrainableMethod('fail');
        $worker->trainableInvoke('fail');
    }

    public function testUnregisteredAndNonPublicMethodsCannotBeInvoked(): void
    {
        $worker = new TrainableWorker();
        $this->expectException(\InvalidArgumentException::class);
        $worker->trainableInvoke('lookup', 'one');
    }

    public function testFiltersHooksAndObjBehaviorsRunForMethodAndModelPaths(): void
    {
        $worker = new TrainableWorker();
        $worker->registerTrainableMethod('lookup', new TrainableMethod(2, 1.0, true, 2));
        $hook = 'automata.trainable.v1.' . strtolower(str_replace('\\', '.', TrainableWorker::class)) . '.lookup';
        $sources = [];
        $behaviors = [];
        Dev::filter($hook . '.input', fn (array $args) => [$args[0] . '!']);
        Dev::filter($hook . '.output', fn (string $result) => strtoupper($result) . '!');
        Dev::action($hook . '.after', function (array $event) use (&$sources): void {
            $sources[] = $event['source'];
        });
        $worker->when('OnTrainableReturned', function ($event, $meta) use (&$behaviors): void {
            $behaviors[] = $meta->info;
        });
        Dev::up();
        try {
            self::assertSame('ANSWER:X!!', $worker->trainableInvoke('lookup', 'x'));
            self::assertSame('ANSWER:X!!', $worker->trainableInvoke('lookup', 'x'));
            self::assertSame('ANSWER:X!!', $worker->trainableInvoke('lookup', 'x'));
            self::assertSame(['method', 'method', 'model'], $sources);
            self::assertSame($sources, $behaviors);
            self::assertSame(2, $worker->calls);
        } finally {
            Dev::down();
        }
    }

    public function testMethodRegisteredBehaviorRunsOnceOnSourceAndPrediction(): void
    {
        $worker = new TrainableWorker();
        $worker->registerAttributedTrainableMethods();
        $events = [];
        $worker->when('OnTagReady', function ($event, $meta) use (&$events): void {
            $events[] = [$meta->info, $meta->data['output']];
        });

        self::assertSame('tag:x', $worker->tag('x'));
        self::assertSame([], $events); // Direct calls do not enter the Trainable lifecycle.
        for ($i = 0; $i < 3; $i++) {
            self::assertSame('tag:x', $worker->trainableInvoke('tag', 'x'));
        }
        self::assertSame([
            ['method', 'tag:x'],
            ['method', 'tag:x'],
            ['model', 'tag:x'],
        ], $events);
    }

    public function testCallableOperationsAndStrategyMatricesComposeWithoutMethodInterception(): void
    {
        $worker = new TrainableWorker();
        $calls = 0;
        $worker->registerTrainableOperation(
            'dynamic',
            function (string $value) use (&$calls): string {
                $calls++;
                return 'dynamic:' . $value;
            },
            new TrainableMethod(2, 1.0, true, 2),
            new TrainingStrategyMatrix([new ExactLookupStrategy()]),
        );
        self::assertSame('dynamic:a', $worker->trainableInvoke('dynamic', 'a'));
        self::assertSame('dynamic:a', $worker->trainableInvoke('dynamic', 'a'));
        self::assertSame('dynamic:a', $worker->trainableInvoke('dynamic', 'a'));
        self::assertSame(2, $calls);
    }

    public function testExistingStateMachineHostKeepsObjHandlersAndState(): void
    {
        $player = new TrainablePlayer('sample');
        $player->initializeTrainable();
        $player->initializeTrainable();
        $events = 0;
        $player->when('OnTrainableReturned', function () use (&$events): void {
            $events++;
        });
        $player->registerTrainableMethod('getName');

        self::assertSame('sample', $player->trainableInvoke('getName'));
        self::assertSame('sample', $player->trainableInvoke('getName'));
        self::assertSame(2, $events);
        self::assertCount(2, $player->trainingRegistry()->observations(TrainablePlayer::class, 'getName'));
        self::assertTrue($player->is(\BlueFission\Behavioral\Behaviors\State::DRAFT));
    }
}

class TrainablePlayer extends Player
{
    use Trainable;
}

class TrainableWorker extends Obj
{
    use Trainable;

    public int $calls = 0;
    public string $locale = 'en';

    public function lookup(string $key): string
    {
        $this->calls++;
        return 'answer:' . $key;
    }

    public function changing(): int
    {
        return ++$this->calls;
    }

    #[TrainableMethod(minimumSamples: 2, allowPrediction: true, minimumMatches: 2, behaviors: ['OnTagReady'])]
    public function tag(string $key): string
    {
        return 'tag:' . $key;
    }

    public function identity(mixed $value): mixed
    {
        return $value;
    }

    public function fail(): never
    {
        throw new \RuntimeException('source failure');
    }
}
