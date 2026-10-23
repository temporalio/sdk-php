<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow\Process;

use Internal\Destroy\Destroyable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\InvalidSuspendException;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\QueryDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\SignalDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\UpdateDispatcher;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Scope;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Workflow;
use Temporal\Workflow\CancellationScopeInterface;

final class ScopeFiberLifecycleTestCase extends TestCase
{
    private WorkerFactoryMock $factory;
    private ScopeLifecycleRootScope $root;
    private ScopeContext $scopeContext;

    /**
     * @return array<string, array{\Closure(): never}>
     */
    public static function pendingSuspensionProvider(): array
    {
        return [
            'bare Deferred promise' => [
                static function (): never {
                    $pending = new Deferred();
                    Workflow::await($pending->promise());
                    throw new \LogicException('Unreachable');
                },
            ],
            'empty Workflow race' => [
                static function (): never {
                    Workflow::race([]);
                    throw new \LogicException('Unreachable');
                },
            ],
        ];
    }

    public function testPromiseReturningHandlerFailsImmediately(): void
    {
        $pending = new Deferred();
        $failure = null;
        $closeCount = 0;
        $closedWith = null;

        $this->root->catch(static function (\Throwable $error) use (&$failure): void {
            $failure = $error;
        });
        $this->root->onClose(
            static function (mixed $value) use (&$closeCount, &$closedWith): void {
                ++$closeCount;
                $closedWith = $value;
            },
        );

        $this->startRoot(static fn() => $pending->promise());

        self::assertInstanceOf(InvalidSuspendException::class, $failure);
        self::assertStringContainsString(
            'Promise-returning workflow handlers are not supported',
            $failure->getMessage(),
        );
        self::assertSame(1, $closeCount);
        self::assertSame($failure, $closedWith);
    }

    public function testGeneratorReturningHandlerFailsImmediately(): void
    {
        $failure = null;

        $this->root->catch(static function (\Throwable $error) use (&$failure): void {
            $failure = $error;
        });

        $this->startRoot(static fn(): \Generator => (static function (): \Generator {
            yield 1;
        })());

        self::assertInstanceOf(InvalidSuspendException::class, $failure);
        self::assertStringContainsString(
            'Generator workflow handlers are no longer supported',
            $failure->getMessage(),
        );
    }

    public function testSynchronousFailureCanStageTerminalCommandFromRejectionCallback(): void
    {
        $expected = new \RuntimeException('root workflow failed');
        $observedContext = null;

        $this->root->catch(function (\Throwable $error) use (&$observedContext): void {
            $observedContext = Workflow::getCurrentContext();
            $this->scopeContext->complete([], $error);
        });

        $this->startRoot(static fn() => throw $expected);

        $commands = \iterator_to_array($this->factory->getQueue());

        self::assertSame($this->scopeContext, $observedContext);
        self::assertCount(1, $commands);
        self::assertInstanceOf(CompleteWorkflow::class, $commands[0]);
        self::assertSame($expected, $commands[0]->getFailure());
    }

    /**
     * @param \Closure(): never $suspend
     */
    #[DataProvider('pendingSuspensionProvider')]
    public function testCancelledPendingScopeSettlesAndAwaitReceivesCanceledFailure(
        \Closure $suspend,
    ): void {
        $child = null;
        $childFailure = null;
        $awaitFailure = null;
        $rootResult = null;

        $this->root->then(static function (mixed $value) use (&$rootResult): void {
            $rootResult = $value;
        });

        $this->startRoot(
            static function () use (
                $suspend,
                &$child,
                &$childFailure,
                &$awaitFailure,
            ): string {
                $child = Workflow::async($suspend);
                $child->catch(
                    static function (\Throwable $error) use (&$childFailure): void {
                        $childFailure = $error;
                    },
                );

                $child->cancel();

                try {
                    $child->await();
                } catch (CanceledFailure $error) {
                    $awaitFailure = $error;
                }

                return 'root completed';
            },
        );
        $this->flush();

        self::assertInstanceOf(CancellationScopeInterface::class, $child);
        self::assertTrue($child->isCancelled());
        self::assertInstanceOf(CanceledFailure::class, $childFailure);
        self::assertInstanceOf(CanceledFailure::class, $awaitFailure);
        self::assertSame('root completed', $rootResult);
    }

    public function testCancelCompletedScopeIsNoOp(): void
    {
        $child = null;
        $childResult = null;
        $cancelFired = false;

        $this->startRoot(static function () use (&$child): void {
            $child = Workflow::async(static fn(): string => 'completed');
        });

        self::assertInstanceOf(CancellationScopeInterface::class, $child);
        $child->then(static function (mixed $value) use (&$childResult): void {
            $childResult = $value;
        });
        $child->onCancel(static function () use (&$cancelFired): void {
            $cancelFired = true;
        });

        self::assertSame('completed', $childResult);
        self::assertFalse($child->isCancelled());

        $child->cancel();
        $this->flush();

        self::assertFalse($child->isCancelled());
        self::assertFalse($cancelFired);
        self::assertSame('completed', $childResult);
    }

    public function testCompletedChildScopeIsReleasedWithoutCycleCollection(): void
    {
        $child = null;
        $gcWasEnabled = \gc_enabled();
        \gc_disable();

        try {
            $this->startRoot(static function () use (&$child): void {
                $child = Workflow::async(static fn(): string => 'completed');
            });

            self::assertInstanceOf(CancellationScopeInterface::class, $child);
            $reference = \WeakReference::create($child);
            unset($child);

            self::assertNull($reference->get());
        } finally {
            $gcWasEnabled and \gc_enable();
        }
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
        $prototype = new WorkflowPrototype('scope-fiber-lifecycle-test', null, new \ReflectionClass($workflow));
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

        $context = new WorkflowContext(
            $services,
            $services->client,
            $instance,
            new Input(),
            EncodedValues::empty(),
        );
        $context->setReadonly(false);
        $this->root = new ScopeLifecycleRootScope($services);
        $this->scopeContext = $this->root->bind($context);
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

    private function flush(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->factory->tick();
        }
    }
}

final class ScopeLifecycleRootScope extends Scope
{
    public function bind(WorkflowContext $context): ScopeContext
    {
        $this->setContext($context);

        return $this->scopeContext;
    }
}
