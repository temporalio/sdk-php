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
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\QueryDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\SignalDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\UpdateDispatcher;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Support\StackRenderer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Workflow;

final class ScopeFiberScaleAndTraceTestCase extends TestCase
{
    private const SCOPE_COUNT = 100;

    private WorkerFactoryMock $factory;
    private ScopeLifecycleRootScope $root;
    private ScopeContext $scopeContext;

    public function testManyConcurrentScopesAllResumeAndAreReleased(): void
    {
        $gate = new Deferred();
        $finished = 0;
        $scopes = [];
        $gcWasEnabled = \gc_enabled();
        \gc_disable();

        try {
            $this->startRoot(static function () use ($gate, &$finished, &$scopes): string {
                for ($i = 0; $i < self::SCOPE_COUNT; ++$i) {
                    $scopes[] = Workflow::async(static function () use ($gate, &$finished): void {
                        Workflow::await($gate->promise());
                        ++$finished;
                    });
                }

                Workflow::all($scopes);

                return 'done';
            });
            $this->flush();

            self::assertSame(0, $finished);

            $gate->resolve(null);
            $this->flush();

            self::assertSame(self::SCOPE_COUNT, $finished);

            $references = \array_map(static fn(object $scope): \WeakReference => \WeakReference::create($scope), $scopes);
            $scopes = [];

            $alive = \array_filter($references, static fn(\WeakReference $ref): bool => $ref->get() !== null);
            self::assertSame([], $alive);
        } finally {
            $gcWasEnabled and \gc_enable();
        }
    }

    public function testManyConcurrentScopesAreTornDownOnDestroy(): void
    {
        $cleanups = 0;
        $gcWasEnabled = \gc_enabled();
        \gc_disable();

        try {
            $this->startRoot(static function () use (&$cleanups): void {
                for ($i = 0; $i < self::SCOPE_COUNT; ++$i) {
                    Workflow::async(static function () use (&$cleanups): void {
                        try {
                            Workflow::await(static fn(): bool => false);
                        } finally {
                            ++$cleanups;
                        }
                    });
                }

                Workflow::await(static fn(): bool => false);
            });

            self::assertSame(0, $cleanups);

            $this->root->destroy();

            self::assertSame(self::SCOPE_COUNT, $cleanups);
        } finally {
            $gcWasEnabled and \gc_enable();
        }
    }

    public function testStackTraceTakenInsideAWorkflowFiberHidesTheSdkMachinery(): void
    {
        $rendered = null;

        $this->startRoot(static function () use (&$rendered): string {
            Workflow::async(static function () use (&$rendered): void {
                $rendered = StackRenderer::renderString(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
            });

            Workflow::await(static fn(): bool => false);

            return 'done';
        });
        $this->flush();

        self::assertIsString($rendered);
        self::assertStringNotContainsString('Internal/Workflow/Process/Scope.php', $rendered);
        self::assertStringNotContainsString('Internal/Workflow/Process/DeferredFiber.php', $rendered);
        self::assertStringContainsString('ScopeFiberScaleAndTraceTestCase.php', $rendered);
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
        $prototype = new WorkflowPrototype('scope-fiber-scale-test', null, new \ReflectionClass($workflow));
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
