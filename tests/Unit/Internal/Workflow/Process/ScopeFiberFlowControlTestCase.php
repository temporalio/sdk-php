<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow\Process;

use Internal\Destroy\Destroyable;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Exception\DestructMemorizedInstanceException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\InvalidSuspendException;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\QueryDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\SignalDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\UpdateDispatcher;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Workflow;
use Temporal\Workflow\CancellationScopeInterface;

final class ScopeFiberFlowControlTestCase extends TestCase
{
    private WorkerFactoryMock $factory;
    private ScopeLifecycleRootScope $root;
    private ScopeContext $scopeContext;

    public function testCancellationUnwindsASuspendedScopeThroughItsFinallyBlock(): void
    {
        $log = [];

        $this->startRoot(static function () use (&$log): string {
            $child = Workflow::async(static function () use (&$log): void {
                try {
                    $log[] = 'child suspended';
                    Workflow::await(static fn(): bool => false);
                } finally {
                    $log[] = 'child cleanup';
                }
            });

            $child->cancel();

            try {
                $child->await();
            } catch (CanceledFailure) {
                $log[] = 'parent observed cancellation';
            }

            return 'done';
        });
        $this->flush();

        self::assertSame(
            ['child suspended', 'child cleanup', 'parent observed cancellation'],
            $log,
        );
    }

    public function testSuspendingInsideFinallyOfACancelledScopeIsInterrupted(): void
    {
        $log = [];
        $gate = new Deferred();
        $cleanupFailure = null;

        $this->startRoot(static function () use (&$log, $gate, &$cleanupFailure): string {
            $child = Workflow::async(static function () use (&$log, $gate, &$cleanupFailure): void {
                try {
                    Workflow::await(static fn(): bool => false);
                } finally {
                    $log[] = 'cleanup started';

                    try {
                        Workflow::await($gate->promise());
                        $log[] = 'cleanup awaited';
                    } catch (\Throwable $error) {
                        $cleanupFailure = $error;
                    }

                    $log[] = 'cleanup finished';
                }
            });

            $child->cancel();

            return 'done';
        });
        $this->flush();

        $gate->resolve(null);
        $this->flush();

        self::assertSame(['cleanup started', 'cleanup finished'], $log);
        self::assertInstanceOf(CanceledFailure::class, $cleanupFailure);
    }

    public function testForeignFiberSuspensionFailsTheScope(): void
    {
        $failure = null;

        $this->root->catch(static function (\Throwable $error) use (&$failure): void {
            $failure = $error;
        });

        $this->startRoot(static function (): void {
            \Fiber::suspend('not a workflow suspension');
        });
        $this->flush();

        self::assertInstanceOf(InvalidSuspendException::class, $failure);
        self::assertStringContainsString('string', $failure->getMessage());
    }

    public function testSuspendingFromAPromiseCallbackIsRejected(): void
    {
        $gate = new Deferred();
        $callbackFailure = null;

        $this->startRoot(static function () use ($gate, &$callbackFailure): string {
            $gate->promise()->then(static function () use (&$callbackFailure): void {
                try {
                    Workflow::await(static fn(): bool => true);
                } catch (\Throwable $error) {
                    $callbackFailure = $error;
                }
            });

            Workflow::await(static fn(): bool => true);

            return 'done';
        });

        $gate->resolve(null);
        $this->flush();

        self::assertInstanceOf(InvalidSuspendException::class, $callbackFailure);
    }

    public function testFailureInsideNestedScopesPropagatesToTheOutermostAwait(): void
    {
        $expected = new \RuntimeException('inner failed');
        $observed = null;
        $innerCleanupRan = false;

        $this->startRoot(static function () use ($expected, &$observed, &$innerCleanupRan): string {
            $outer = Workflow::async(static function () use ($expected, &$innerCleanupRan): void {
                $inner = Workflow::async(static function () use ($expected, &$innerCleanupRan): void {
                    try {
                        throw $expected;
                    } finally {
                        $innerCleanupRan = true;
                    }
                });

                $inner->await();
            });

            try {
                $outer->await();
            } catch (\Throwable $error) {
                $observed = $error;
            }

            return 'done';
        });
        $this->flush();

        self::assertTrue($innerCleanupRan);
        self::assertSame($expected, $observed);
    }

    public function testCancellingAnOuterScopeUnwindsItsNestedChild(): void
    {
        $log = [];

        $this->startRoot(static function () use (&$log): string {
            $outer = Workflow::async(static function () use (&$log): void {
                $inner = Workflow::async(static function () use (&$log): void {
                    try {
                        Workflow::await(static fn(): bool => false);
                    } finally {
                        $log[] = 'inner cleanup';
                    }
                });

                try {
                    $inner->await();
                } finally {
                    $log[] = 'outer cleanup';
                }
            });

            $outer->cancel();

            try {
                $outer->await();
            } catch (CanceledFailure) {
                $log[] = 'parent observed cancellation';
            }

            return 'done';
        });
        $this->flush();

        self::assertContains('inner cleanup', $log);
        self::assertContains('outer cleanup', $log);
        self::assertSame('parent observed cancellation', $log[\count($log) - 1]);
    }

    public function testDetachedScopeIgnoresParentCancellationButHonoursItsOwn(): void
    {
        $log = [];
        $detached = null;

        $this->startRoot(static function () use (&$log, &$detached): string {
            $outer = Workflow::async(static function () use (&$log, &$detached): void {
                $detached = Workflow::asyncDetached(static function () use (&$log): void {
                    try {
                        Workflow::await(static fn(): bool => false);
                    } finally {
                        $log[] = 'detached cleanup';
                    }
                });

                Workflow::await(static fn(): bool => false);
            });

            $outer->cancel();
            $log[] = 'outer cancelled';

            return 'done';
        });
        $this->flush();

        self::assertSame(['outer cancelled'], $log);
        self::assertInstanceOf(CancellationScopeInterface::class, $detached);
        self::assertTrue($detached->isDetached());
        self::assertFalse($detached->isCancelled());

        $detached->cancel();
        $this->flush();

        self::assertSame(['outer cancelled', 'detached cleanup'], $log);
    }

    public function testDetachedCleanupStartedInsideACancelledScopeStillCompletes(): void
    {
        $log = [];
        $gate = new Deferred();
        $cleanup = null;

        $this->startRoot(static function () use (&$log, $gate, &$cleanup): string {
            $child = Workflow::async(static function () use (&$log, $gate, &$cleanup): void {
                try {
                    Workflow::await(static fn(): bool => false);
                } finally {
                    $cleanup = Workflow::asyncDetached(static function () use (&$log, $gate): void {
                        Workflow::await($gate->promise());
                        $log[] = 'compensated';
                    });
                }
            });

            $child->cancel();

            return 'done';
        });
        $this->flush();

        self::assertSame([], $log);
        self::assertInstanceOf(CancellationScopeInterface::class, $cleanup);
        self::assertFalse($cleanup->isCancelled());

        $gate->resolve(null);
        $this->flush();

        self::assertSame(['compensated'], $log);
    }

    public function testDetachedScopeOutlivesTheScopeThatStartedIt(): void
    {
        $log = [];
        $gate = new Deferred();
        $detached = null;

        $this->startRoot(static function () use (&$log, $gate, &$detached): string {
            $owner = Workflow::async(static function () use (&$log, $gate, &$detached): void {
                $detached = Workflow::asyncDetached(static function () use (&$log, $gate): void {
                    Workflow::await($gate->promise());
                    $log[] = 'detached finished';
                });

                $log[] = 'owner finished';
            });

            $owner->await();

            return 'done';
        });
        $this->flush();

        self::assertSame(['owner finished'], $log);

        $gate->resolve(null);
        $this->flush();

        self::assertSame(['owner finished', 'detached finished'], $log);
        self::assertInstanceOf(CancellationScopeInterface::class, $detached);
    }

    public function testReadonlyContextReportsUninitializedWorkflowRatherThanSuspendMisuse(): void
    {
        $this->scopeContext->setReadonly(true);
        Workflow::setCurrentContext($this->scopeContext);

        try {
            Workflow::await(static fn(): bool => true);
            self::fail('Expected a suspending call to be rejected.');
        } catch (\Throwable $error) {
            self::assertInstanceOf(\RuntimeException::class, $error);
            self::assertNotInstanceOf(InvalidSuspendException::class, $error);
            self::assertSame('Workflow is not initialized.', $error->getMessage());
        }
    }

    public function testDestroyUnwindsThreeLevelsDeepestFirst(): void
    {
        $torndown = [];
        $gcWasEnabled = \gc_enabled();
        \gc_disable();

        try {
            $this->startRoot(static function () use (&$torndown): void {
                Workflow::async(static function () use (&$torndown): void {
                    Workflow::async(static function () use (&$torndown): void {
                        try {
                            Workflow::await(static fn(): bool => false);
                        } finally {
                            $torndown[] = 'grandchild';
                        }
                    });

                    try {
                        Workflow::await(static fn(): bool => false);
                    } finally {
                        $torndown[] = 'child';
                    }
                });

                try {
                    Workflow::await(static fn(): bool => false);
                } finally {
                    $torndown[] = 'root';
                }
            });

            self::assertSame([], $torndown);

            $this->root->destroy();

            self::assertSame(['grandchild', 'child', 'root'], $torndown);
        } finally {
            $gcWasEnabled and \gc_enable();
        }
    }

    public function testDestroyDeliversACatchableFailureToWorkflowCode(): void
    {
        $caught = null;
        $gcWasEnabled = \gc_enabled();
        \gc_disable();

        try {
            $this->startRoot(static function () use (&$caught): void {
                try {
                    Workflow::await(static fn(): bool => false);
                } catch (\Throwable $error) {
                    $caught = $error;
                }
            });

            $this->root->destroy();

            self::assertInstanceOf(DestructMemorizedInstanceException::class, $caught);
        } finally {
            $gcWasEnabled and \gc_enable();
        }
    }

    public function testAwaitPredicateIsAllowedToSuspendTheEnclosingFiber(): void
    {
        $gate = new Deferred();
        $log = [];
        $flag = false;

        $this->startRoot(static function () use ($gate, &$log, &$flag): string {
            $log[] = 'before await';

            Workflow::await(static function () use ($gate, &$flag, &$log): bool {
                $log[] = 'predicate entered';
                Workflow::await($gate->promise());
                $log[] = 'predicate resumed';

                return $flag;
            });

            $log[] = 'after await';

            return 'done';
        });
        $this->flush();

        self::assertSame(['before await', 'predicate entered'], $log);

        $flag = true;
        $gate->resolve(null);
        $this->flush();

        self::assertSame(
            ['before await', 'predicate entered', 'predicate resumed', 'after await'],
            $log,
        );
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
        $prototype = new WorkflowPrototype('scope-fiber-flow-control-test', null, new \ReflectionClass($workflow));
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
        for ($i = 0; $i < 8; ++$i) {
            $this->factory->tick();
        }
    }
}
