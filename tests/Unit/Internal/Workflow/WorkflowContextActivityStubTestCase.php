<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Activity\DummyLocalActivity;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;

#[CoversClass(WorkflowContext::class)]
final class WorkflowContextActivityStubTestCase extends TestCase
{
    public function testLocalActivityStubNeedsLocalActivityOptions(): void
    {
        $services = ServiceContainer::fromWorkerFactory(
            new WorkerFactoryMock(DataConverter::createDefault()),
            ExceptionInterceptor::createDefault(),
            new SimplePipelineProvider(),
            new NullLogger(),
        );
        $workflow = new class {
            public function handle(): void {}
        };
        $prototype = new WorkflowPrototype('Workflow', new \ReflectionMethod($workflow, 'handle'), new \ReflectionClass($workflow));
        $instance = new WorkflowInstance($prototype, $workflow);
        $context = new WorkflowContext($services, $services->client, $instance, new Input(), EncodedValues::empty());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Local activity can be used only with LocalActivityOptions');

        $context->newActivityStub(DummyLocalActivity::class, ActivityOptions::new());
    }
}
