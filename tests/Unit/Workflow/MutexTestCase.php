<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Workflow;

use Internal\Destroy\Destroyable;
use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\QueryDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\SignalDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\UpdateDispatcher;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Scope;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Workflow;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\Mutex;

final class MutexTestCase extends TestCase
{
    private WorkerFactoryMock $factory;
    private MutexWorkflowContext $context;
    private MutexRootScope $root;

    public function testUncontendedLockCompletesImmediately(): void
    {
        $mutex = new Mutex();
        $acquired = null;

        $this->startRoot(static function () use ($mutex, &$acquired): void {
            $acquired = $mutex->lock();
        });

        self::assertSame($mutex, $acquired);
        self::assertTrue($mutex->isLocked());
        self::assertSame(0, $this->context->pendingConditionCount());

        $mutex->unlock();
        self::assertFalse($mutex->isLocked());
    }

    public function testTryLock(): void
    {
        $mutex = new Mutex();

        self::assertTrue($mutex->tryLock());
        self::assertFalse($mutex->tryLock());
        $mutex->unlock();
        self::assertTrue($mutex->tryLock());
    }

    public function testTryLockFailsWhileWaitersAreQueuedEvenWhenUnlocked(): void
    {
        $mutex = new Mutex();

        $this->startRoot(static function () use ($mutex): void {
            $mutex->lock();
            Workflow::async(static function () use ($mutex): void {
                $mutex->lock();
            });
        });

        self::assertSame(1, $this->context->pendingConditionCount());

        $mutex->unlock();
        self::assertFalse($mutex->isLocked());
        self::assertFalse($mutex->tryLock());
    }

    public function testQueuedLocksAcquireInRegistrationOrder(): void
    {
        $mutex = new Mutex();
        $events = [];
        $releaseFirst = false;

        $this->startRoot(static function () use ($mutex, &$events, &$releaseFirst): void {
            $mutex->lock();

            Workflow::async(static function () use ($mutex, &$events, &$releaseFirst): void {
                $mutex->lock();
                $events[] = 'first';
                Workflow::await(static function () use (&$releaseFirst): bool {
                    return $releaseFirst;
                });
                $mutex->unlock();
            });

            Workflow::async(static function () use ($mutex, &$events): void {
                $mutex->lock();
                $events[] = 'second';
                $mutex->unlock();
            });

            Workflow::async(static function () use ($mutex, &$events): void {
                $mutex->lock();
                $events[] = 'third';
                $mutex->unlock();
            });
        });

        self::assertSame([], $events);
        self::assertSame(3, $this->context->pendingConditionCount());

        $mutex->unlock();
        $this->flushConditions();

        self::assertSame(['first'], $events);
        self::assertTrue($mutex->isLocked());

        $releaseFirst = true;
        $this->flushConditions();

        self::assertSame(['first', 'second', 'third'], $events);
        self::assertFalse($mutex->isLocked());
        self::assertSame(0, $this->context->pendingConditionCount());
    }

    public function testCancellingQueuedLockRemovesWaiterAndUnblocksTheNextOne(): void
    {
        $mutex = new Mutex();
        $events = [];
        $cancelled = null;
        $error = null;

        $this->startRoot(static function () use ($mutex, &$events, &$cancelled, &$error): void {
            $mutex->lock();

            $cancelled = Workflow::async(static function () use ($mutex, &$events): void {
                $mutex->lock();
                $events[] = 'cancelled';
            });
            $cancelled->catch(static function (\Throwable $reason) use (&$error): void {
                $error = $reason;
            });

            Workflow::async(static function () use ($mutex, &$events): void {
                $mutex->lock();
                $events[] = 'next';
                $mutex->unlock();
            });
        });

        self::assertInstanceOf(CancellationScopeInterface::class, $cancelled);
        self::assertSame(2, $this->context->pendingConditionCount());

        $cancelled->cancel();
        $this->factory->tick();

        self::assertTrue($cancelled->isCancelled());
        self::assertInstanceOf(CanceledFailure::class, $error);
        self::assertSame([], $events);

        $mutex->unlock();
        $this->flushConditions();

        self::assertSame(['next'], $events);
        self::assertFalse($mutex->isLocked());
    }

    protected function setUp(): void
    {
        $this->factory = new WorkerFactoryMock(DataConverter::createDefault());
        $services = ServiceContainer::fromWorkerFactory(
            $this->factory,
            ExceptionInterceptor::createDefault(),
            new SimplePipelineProvider(),
            new StderrLogger(),
        );

        $workflow = new \stdClass();
        $prototype = new WorkflowPrototype('mutex-test', null, new \ReflectionClass($workflow));
        $instance = $this->createMockForIntersectionOfInterfaces([
            WorkflowInstanceInterface::class,
            Destroyable::class,
        ]);
        $instance->method('getQueryDispatcher')
            ->willReturn(new QueryDispatcher($prototype, $workflow));
        $instance->method('getSignalDispatcher')
            ->willReturn(new SignalDispatcher($prototype, $workflow));
        $instance->method('getUpdateDispatcher')
            ->willReturn(new UpdateDispatcher($prototype, $workflow));

        $this->context = new MutexWorkflowContext(
            $services,
            $services->client,
            $instance,
            new Input(),
            EncodedValues::empty(),
        );
        $this->context->setReadonly(false);
        $this->root = new MutexRootScope($services);
        $this->root->bind($this->context);
    }

    protected function tearDown(): void
    {
        Workflow::setCurrentContext(null);
    }

    private function startRoot(callable $handler): void
    {
        $this->root->start(
            static fn(ValuesInterface $values): mixed => $handler(),
            EncodedValues::empty(),
            false,
        );
    }

    private function flushConditions(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->context->resolveConditions();
            $this->factory->tick();
        }
    }
}

final class MutexWorkflowContext extends WorkflowContext
{
    public function pendingConditionCount(): int
    {
        return \array_sum(\array_map(\count(...), $this->awaits));
    }
}

final class MutexRootScope extends Scope
{
    public function bind(WorkflowContext $context): ScopeContext
    {
        $this->setContext($context);

        return $this->scopeContext;
    }
}
