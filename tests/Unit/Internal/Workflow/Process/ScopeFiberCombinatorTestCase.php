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
use Temporal\Exception\Failure\CanceledFailure;
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

final class ScopeFiberCombinatorTestCase extends TestCase
{
    private WorkerFactoryMock $factory;
    private ScopeLifecycleRootScope $root;
    private ScopeContext $scopeContext;

    public function testAllResolvesInInputOrderRegardlessOfCompletionOrder(): void
    {
        $first = new Deferred();
        $second = new Deferred();
        $result = null;

        $this->startRoot(static function () use ($first, $second, &$result): string {
            $result = Workflow::all([
                Workflow::async(static fn(): mixed => Workflow::await($first->promise())),
                Workflow::async(static fn(): mixed => Workflow::await($second->promise())),
            ]);

            return 'done';
        });
        $this->flush();

        $second->resolve('second');
        $this->flush();
        self::assertNull($result);

        $first->resolve('first');
        $this->flush();

        self::assertSame(['first', 'second'], $result);
    }

    public function testAllRejectsAsSoonAsAnyMemberFails(): void
    {
        $expected = new \RuntimeException('member failed');
        $pending = new Deferred();
        $failing = new Deferred();
        $observed = null;

        $this->startRoot(static function () use ($pending, $failing, &$observed): string {
            try {
                Workflow::all([
                    Workflow::async(static fn(): mixed => Workflow::await($pending->promise())),
                    Workflow::async(static fn(): mixed => Workflow::await($failing->promise())),
                ]);
            } catch (\Throwable $error) {
                $observed = $error;
            }

            return 'done';
        });
        $this->flush();

        $failing->reject($expected);
        $this->flush();

        self::assertSame($expected, $observed);
    }

    public function testRaceLeavesTheLosingScopeRunning(): void
    {
        $winner = new Deferred();
        $loser = new Deferred();
        $log = [];
        $loserScope = null;

        $this->startRoot(static function () use ($winner, $loser, &$log, &$loserScope): string {
            $loserScope = Workflow::async(static function () use ($loser, &$log): void {
                Workflow::await($loser->promise());
                $log[] = 'loser finished';
            });

            $log[] = 'raced: ' . Workflow::race([
                Workflow::async(static fn(): mixed => Workflow::await($winner->promise())),
                $loserScope,
            ]);

            return 'done';
        });
        $this->flush();

        $winner->resolve('winner');
        $this->flush();

        self::assertSame(['raced: winner'], $log);
        self::assertFalse($loserScope->isCancelled());

        $loser->resolve(null);
        $this->flush();

        self::assertSame(['raced: winner', 'loser finished'], $log);
    }

    public function testCancellingAScopeAwaitingAllCancelsEveryMember(): void
    {
        $log = [];
        $outer = null;

        $this->startRoot(static function () use (&$log, &$outer): string {
            $outer = Workflow::async(static function () use (&$log): void {
                Workflow::all([
                    Workflow::async(static function () use (&$log): void {
                        try {
                            Workflow::await(static fn(): bool => false);
                        } finally {
                            $log[] = 'member one cleanup';
                        }
                    }),
                    Workflow::async(static function () use (&$log): void {
                        try {
                            Workflow::await(static fn(): bool => false);
                        } finally {
                            $log[] = 'member two cleanup';
                        }
                    }),
                ]);
            });

            $outer->cancel();

            return 'done';
        });
        $this->flush();

        self::assertContains('member one cleanup', $log);
        self::assertContains('member two cleanup', $log);
    }

    public function testAwaitOnAnAlreadyCompletedScopeReturnsItsValue(): void
    {
        $observed = null;

        $this->startRoot(static function () use (&$observed): string {
            $done = Workflow::async(static fn(): string => 'value');
            $observed = $done->await();

            return 'done';
        });
        $this->flush();

        self::assertSame('value', $observed);
    }

    public function testAwaitOnAnAlreadyFailedScopeRethrowsItsFailure(): void
    {
        $expected = new \RuntimeException('already failed');
        $observed = null;

        $this->startRoot(static function () use ($expected, &$observed): string {
            $failed = Workflow::async(static fn() => throw $expected);

            try {
                $failed->await();
            } catch (\Throwable $error) {
                $observed = $error;
            }

            return 'done';
        });
        $this->flush();

        self::assertSame($expected, $observed);
    }

    public function testTwoScopesCanAwaitTheSameScope(): void
    {
        $gate = new Deferred();
        $seen = [];

        $this->startRoot(static function () use ($gate, &$seen): string {
            $shared = Workflow::async(static fn(): mixed => Workflow::await($gate->promise()));

            $watchers = [
                Workflow::async(static function () use ($shared, &$seen): void {
                    $seen[] = 'a:' . $shared->await();
                }),
                Workflow::async(static function () use ($shared, &$seen): void {
                    $seen[] = 'b:' . $shared->await();
                }),
            ];

            Workflow::all($watchers);

            return 'done';
        });
        $this->flush();

        $gate->resolve('shared');
        $this->flush();

        \sort($seen);
        self::assertSame(['a:shared', 'b:shared'], $seen);
    }

    public function testCancellationReasonReachesTheSuspendedScope(): void
    {
        $reason = new CanceledFailure('explicit reason');
        $observed = null;

        $this->startRoot(static function () use (&$observed): string {
            $child = Workflow::async(static function () use (&$observed): void {
                try {
                    Workflow::await(static fn(): bool => false);
                } catch (\Throwable $error) {
                    $observed = $error;
                }
            });

            $child->cancel();

            return 'done';
        });
        $this->flush();

        self::assertInstanceOf(CanceledFailure::class, $observed);
    }

    public function testCancellingTwiceRunsCleanupOnce(): void
    {
        $cleanupCount = 0;

        $this->startRoot(static function () use (&$cleanupCount): string {
            $child = Workflow::async(static function () use (&$cleanupCount): void {
                try {
                    Workflow::await(static fn(): bool => false);
                } finally {
                    ++$cleanupCount;
                }
            });

            $child->cancel();
            $child->cancel();

            return 'done';
        });
        $this->flush();

        self::assertSame(1, $cleanupCount);
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
        $prototype = new WorkflowPrototype('scope-fiber-combinator-test', null, new \ReflectionClass($workflow));
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
