<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\LocalActivityOptions;
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

    public static function provideUserCycles(): iterable
    {
        foreach (EvictionShapeWorkflow::USER_CYCLES as $shape) {
            yield $shape => [$shape];
        }
    }

    #[DataProvider('provideUserCycles')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEvictionReleasesTheWorkflowAndLeavesOnlyCyclesOfUserObjects(string $shape): void
    {
        $this->runAndEvict($shape);

        self::assertNull(EvictionShapeWorkflow::$instance->get());
        self::assertSame([], EvictionShapeWorkflow::$observed);
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
        'held_any',
        'held_some',
        'held_race',
        'held_reduce',
        'combinator_then',
        'captured_timer',
        'nested_temporal',
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
        'combinator_fork',
        'react_fork',
        'react_nested',
        'react_nested_temporal',
        'temporal_nested_react',
        'user_canceller',
        'user_canceller_promise',
        'user_canceller_chain',
        'user_canceller_fork',
        'react_race_fork',
        'react_any_fork',
        'react_mixed_fork',
        'temporal_mixed_fork',
        'held_temporal_fork',
        'held_react_fork',
        'held_react_mixed_fork',
        'held_temporal_mixed_fork',
        'held_some_mixed_fork',
        'held_reduce_mixed_fork',
        'held_race_mixed_fork',
        'local_activity',
        'child_workflow',
        'side_effect',
        'get_version',
    ];

    public const USER_CYCLES = [
        'held_user_fork',
        'held_user_cycle',
    ];

    public static array $observed = [];
    public static \WeakReference $instance;
    private string $shape = '';

    #[WorkflowMethod(name: 'EvictionShapeWorkflow')]
    public function handler(string $shape): iterable
    {
        self::$instance = \WeakReference::create($this);
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
                'held_combinator' => self::held($never, Promise::all([$timer(), $timer()])),
                'held_any' => self::held($never, Promise::any([$timer(), $timer()])),
                'held_some' => self::held($never, Promise::some([$timer(), $timer()], 1)),
                'held_race' => self::held($never, Promise::race([$timer(), $timer()])),
                'held_reduce' => self::held($never, Promise::reduce([$timer(), $timer()], static fn(mixed $carry, mixed $value): mixed => $value)),
                'combinator_then' => Promise::all([$timer(), $timer()])->then($observe, $observe),
                'captured_timer' => self::captured($timer, $observe),
                'nested_temporal' => Promise::all([Promise::any([$timer(), $timer()]), $timer()]),
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
                'combinator_fork' => self::fork(Promise::all([$timer(), $timer()]), $observe),
                'react_fork' => self::fork(all([$timer(), $timer()]), $observe),
                'react_nested' => all([all([$timer(), $timer()]), race([$timer(), $timer()]), $timer()]),
                'react_nested_temporal' => all([Promise::all([$timer(), $timer()]), $timer()]),
                'temporal_nested_react' => Promise::all([all([$timer(), $timer()]), $timer()]),
                'user_canceller' => self::cancellable(),
                'user_canceller_promise' => self::cancellable()->promise(),
                'user_canceller_chain' => self::cancellable()->promise()->then($observe)->then($observe, $observe),
                'user_canceller_fork' => self::fork(self::cancellable()->promise(), $observe),
                'react_race_fork' => self::fork(race([$timer(), $timer()]), $observe),
                'react_any_fork' => self::fork(any([$timer(), $timer()]), $observe),
                'react_mixed_fork' => self::fork(all([self::cancellable()->promise(), $timer()]), $observe),
                'temporal_mixed_fork' => self::fork(Promise::all([self::cancellable()->promise(), $timer()]), $observe),
                'held_temporal_fork' => self::held($never, self::fork(Promise::all([$timer(), $timer()]), $observe)),
                'held_react_fork' => self::held($never, self::fork(all([$timer(), $timer()]), $observe)),
                'held_react_mixed_fork' => self::held($never, self::fork(all([self::cancellable()->promise(), $timer()]), $observe)),
                'held_temporal_mixed_fork' => self::held($never, self::fork(Promise::all([self::cancellable()->promise(), $timer()]), $observe)),
                'held_some_mixed_fork' => self::held($never, self::fork(Promise::some([self::cancellable()->promise(), $timer()], 1), $observe)),
                'held_reduce_mixed_fork' => self::held($never, self::fork(Promise::reduce([self::cancellable()->promise(), $timer()], static fn(mixed $carry, mixed $value): mixed => $value), $observe)),
                'held_race_mixed_fork' => self::held($never, self::fork(Promise::race([self::cancellable()->promise(), $timer()]), $observe)),
                'held_user_fork' => self::held($never, self::fork(self::cancellable()->promise(), $observe)),
                'held_user_cycle' => self::held($never, self::cycle()),
                'local_activity' => Workflow::newUntypedActivityStub(
                    LocalActivityOptions::new()->withStartToCloseTimeout(10),
                )->execute('LocalActivity'),
                'child_workflow' => Workflow::newUntypedChildWorkflowStub('ChildWorkflow')->execute(),
                'side_effect' => Workflow::sideEffect(static fn(): int => 1),
                'get_version' => Workflow::getVersion('change', Workflow::DEFAULT_VERSION, 1),
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

    private static function held(\Closure $never, mixed $held): \Generator
    {

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

    private static function cycle(): \stdClass
    {
        $object = new \stdClass();
        $object->self = $object;

        return $object;
    }

    private static function cancellable(): Deferred
    {
        return new Deferred(static function (): void {
            self::$observed[] = 'canceller';
        });
    }

    private static function fork(PromiseInterface $combined, \Closure $observe): mixed
    {
        $combined->then($observe, $observe);

        return $combined;
    }
}
