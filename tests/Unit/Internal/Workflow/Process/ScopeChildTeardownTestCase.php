<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow\Process;

use Internal\Destroy\Destroyable;
use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\DestructMemorizedInstanceException;
use Temporal\Exception\ExceptionInterceptor;
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

final class ScopeChildTeardownTestCase extends TestCase
{
    private ScopeTeardownRootScope $root;

    public function testDestroyTearsDownLiveChildScopesWithoutCycleCollection(): void
    {
        $torndown = [];
        $childFailure = null;
        $rootFailure = null;
        $gcWasEnabled = \gc_enabled();
        \gc_disable();

        try {
            $this->root->catch(static function (\Throwable $error) use (&$rootFailure): void {
                $rootFailure = $error;
            });

            $this->startRoot(static function () use (&$torndown, &$childFailure): void {
                $child = Workflow::async(static function () use (&$torndown): void {
                    try {
                        Workflow::await(static fn(): bool => false);
                    } finally {
                        $torndown[] = 'child';
                    }
                });
                $child->catch(static function (\Throwable $error) use (&$childFailure): void {
                    $childFailure = $error;
                });

                try {
                    Workflow::await(static fn(): bool => false);
                } finally {
                    $torndown[] = 'root';
                }
            });

            self::assertSame([], $torndown);

            $this->root->destroy();

            self::assertSame(['child', 'root'], $torndown);
            self::assertInstanceOf(DestructMemorizedInstanceException::class, $childFailure);
            self::assertInstanceOf(DestructMemorizedInstanceException::class, $rootFailure);
        } finally {
            $gcWasEnabled and \gc_enable();
        }
    }

    protected function setUp(): void
    {
        $factory = new WorkerFactoryMock(DataConverter::createDefault());
        $services = ServiceContainer::fromWorkerFactory(
            $factory,
            ExceptionInterceptor::createDefault(),
            new SimplePipelineProvider(),
            new StderrLogger(),
        );

        $workflow = new \stdClass();
        $prototype = new WorkflowPrototype('scope-child-teardown-test', null, new \ReflectionClass($workflow));
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
        $this->root = new ScopeTeardownRootScope($services);
        $this->root->bind($context);
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
}

final class ScopeTeardownRootScope extends Scope
{
    public function bind(WorkflowContext $context): ScopeContext
    {
        $this->setContext($context);

        return $this->scopeContext;
    }
}
