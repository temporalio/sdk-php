<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use React\Promise\Deferred;
use Temporal\Activity\ActivityOptions;
use Temporal\Promise;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Tests\Unit\Framework\WorkerMock;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

use function React\Promise\all;
use function React\Promise\any;
use function React\Promise\race;

final class EvictionGarbageTestCase extends AbstractUnit
{
    private WorkerFactoryInterface $factory;
    private WorkerMock $worker;

    public static function provideShapes(): iterable
    {
        foreach (EvictionShapeWorkflow::SHAPES as $shape) {
            yield $shape => [$shape];
        }
    }

    #[DataProvider('provideShapes')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEvictionLeavesNoGarbageCycles(string $shape): void
    {
        $this->runAndEvict($shape);
        \gc_collect_cycles();
        EvictionShapeWorkflow::$observed = [];

        $this->runAndEvict($shape);

        self::assertSame(0, \gc_collect_cycles());
        self::assertSame([], EvictionShapeWorkflow::$observed);
    }

    protected function setUp(): void
    {
        $this->factory = WorkerFactoryMock::create();
        $this->worker = $this->factory->newWorker();
        $this->worker->registerWorkflowObject(new EvictionShapeWorkflow());
        \gc_collect_cycles();
        \gc_disable();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        \gc_enable();
        Workflow::setCurrentContext(null);

        parent::tearDown();
    }

    private function runAndEvict(string $shape): void
    {
        $this->worker->runWorkflow('EvictionShapeWorkflow', $shape);
        $this->worker->sendSignal('EvictionShapeWorkflow', 'signal');
        $this->worker->sendUpdate('EvictionShapeWorkflow', 'update');
        $this->worker->destroyWorkflow('EvictionShapeWorkflow');

        $this->factory->run($this->worker);
    }
}

#[Workflow\WorkflowInterface]
final class EvictionShapeWorkflow
{
    public const SHAPES = [
        'timer',
        'temporal_all',
        'temporal_any',
        'temporal_race',
        'react_all',
        'react_any',
        'react_race',
        'then_chain',
        'await_parallel',
        'await_with_timeout',
        'async',
        'async_detached',
        'signal',
        'update',
        'nested_generator',
        'held_combinator',
        'combinator_then',
        'captured_timer',
        'nested_temporal',
        'combinator_fork',
        'react_then',
        'nested_temporal_race',
        'user_deferred',
        'mutex_lock',
        'mutex_await',
        'temporal_reduce',
        'async_all',
        'activity',
        'detached_all',
        'rejecting_canceller',
    ];

    public static array $observed = [];
    private string $shape = '';

    #[WorkflowMethod(name: 'EvictionShapeWorkflow')]
    public function handler(string $shape): iterable
    {
        $this->shape = $shape;
        $timer = static fn(): mixed => Workflow::timer(100);
        $observe = static function (mixed $value = null): mixed {
            self::$observed[] = 'callback';
            return $value;
        };
        $never = static fn(): bool => false;

        try {
            yield match ($shape) {
                'timer', 'signal', 'update' => $timer(),
                'temporal_all' => Promise::all([$timer(), $timer()]),
                'temporal_any' => Promise::any([$timer(), $timer()]),
                'temporal_race' => Promise::race([$timer(), $timer()]),
                'react_all' => all([$timer(), $timer()]),
                'react_any' => any([$timer(), $timer()]),
                'react_race' => race([$timer(), $timer()]),
                'then_chain' => $timer()->then($observe)->then($observe, $observe)->catch($observe)->finally($observe),
                'await_parallel' => Workflow::await($never, $timer(), $never),
                'await_with_timeout' => Workflow::awaitWithTimeout(50, $never, $timer()),
                'async' => Workflow::async(static fn(): \Generator => yield $timer()),
                'async_detached' => self::detached($timer, $never),
                'nested_generator' => self::nested($timer),
                'held_combinator' => self::held($timer, $never),
                'combinator_then' => Promise::all([$timer(), $timer()])->then($observe, $observe),
                'captured_timer' => self::captured($timer, $observe),
                'nested_temporal' => Promise::all([Promise::any([$timer(), $timer()]), $timer()]),
                'combinator_fork' => self::fork($timer, $observe),
                'react_then' => all([$timer(), $timer()])->then($observe, $observe),
                'nested_temporal_race' => Promise::all([Promise::race([$timer(), $timer()]), $timer()]),
                'user_deferred' => self::userDeferred($observe),
                'mutex_lock' => self::mutexLock(),
                'mutex_await' => self::mutexAwait(),
                'async_all' => Promise::all([
                    Workflow::async(static fn(): \Generator => yield $timer()),
                    Workflow::async(static fn(): \Generator => yield $timer()),
                ]),
                'activity' => Workflow::newUntypedActivityStub(
                    ActivityOptions::new()->withStartToCloseTimeout(10),
                )->execute('Activity'),
                'rejecting_canceller' => new Deferred(static function (callable $resolve, callable $reject): void {
                    $reject(new \RuntimeException());
                }),
                'temporal_reduce' => Promise::reduce([$timer(), $timer()], static fn(mixed $carry, mixed $value): mixed => $value),
                'detached_all' => Promise::all([Workflow::asyncDetached(static fn(): \Generator => yield $timer()), $timer()]),
            };
        } catch (\Throwable) {
            self::$observed[] = 'catch';
        }
    }

    #[Workflow\SignalMethod(name: 'signal')]
    public function signal(): iterable
    {
        if ($this->shape !== 'signal') {
            return;
        }

        try {
            yield Workflow::timer(100);
        } catch (\Throwable) {
            self::$observed[] = 'catch';
        }
    }

    #[Workflow\UpdateMethod(name: 'update')]
    public function update(): iterable
    {
        if ($this->shape !== 'update') {
            return;
        }

        try {
            yield Workflow::timer(100);
        } catch (\Throwable) {
            self::$observed[] = 'catch';
        }
    }

    private static function detached(\Closure $timer, \Closure $never): mixed
    {
        Workflow::asyncDetached(static fn(): \Generator => yield $timer());

        return Workflow::await($never);
    }

    private static function nested(\Closure $timer): \Generator
    {
        try {
            yield $timer();
        } catch (\Throwable) {
            self::$observed[] = 'catch';
        }
    }

    private static function held(\Closure $timer, \Closure $never): \Generator
    {
        $held = Promise::all([$timer(), $timer()]);

        yield Workflow::await($never);

        return $held;
    }

    private static function captured(\Closure $timer, \Closure $observe): mixed
    {
        $result = $timer();

        return $result->then(static function () use ($result, $observe): mixed {
            return $observe($result);
        });
    }

    private static function userDeferred(\Closure $observe): mixed
    {
        $deferred = new Deferred();
        $deferred->promise()->then($observe, $observe);

        return $deferred;
    }

    private static function mutexLock(): mixed
    {
        $mutex = new Workflow\Mutex();
        $mutex->lock();

        return $mutex->lock();
    }

    private static function mutexAwait(): mixed
    {
        $mutex = new Workflow\Mutex();
        $mutex->lock();

        return $mutex;
    }

    private static function fork(\Closure $timer, \Closure $observe): mixed
    {
        $combined = Promise::all([$timer(), $timer()]);
        $combined->then($observe, $observe);

        return $combined;
    }
}
