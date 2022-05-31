<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Router;

use LogicException;
use React\Promise\Deferred;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Router\StartWorkflow;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\UnitTestCase;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Tests\Unit\Framework\Requests\StartWorkflow as Request;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInfo;

final class StartWorkflowTestCase extends UnitTestCase
{
    private ServiceContainer $services;
    private StartWorkflow $router;

    protected function setUp(): void
    {
        $dataConverter = $this->createMock(DataConverterInterface::class);
        $this->marshaller = $this->createMock(MarshallerInterface::class);

        $this->services = new ServiceContainer(
            $this->createMock(LoopInterface::class),
            $this->createMock(EnvironmentInterface::class),
            $this->createMock(ClientInterface::class),
            $this->createMock(ReaderInterface::class),
            $this->createMock(QueueInterface::class),
            $this->marshaller,
            $dataConverter,
            $this->createMock(ExceptionInterceptorInterface::class),
        );
        $workflowReader = new WorkflowReader(new SelectiveReader([new AnnotationReader(), new AttributeReader()]));
        $this->services->workflows->add($workflowReader->fromClass(DummyWorkflow::class));
        $this->router = new StartWorkflow($this->services);
        $this->workflowContext = new WorkflowContext(
            $this->services,
            $this->services->client,
            $this->createMock(WorkflowInstanceInterface::class),
            new Input(),
            EncodedValues::empty()
        );

        parent::setUp();
    }

    public function testWorkflowIsStartedAndRunning(): void
    {
        $request = new Request(Uuid::v4(), DummyWorkflow::class, EncodedValues::fromValues([]));

        $workflowInfo = new WorkflowInfo();
        $workflowInfo->type->name = 'DummyWorkflow';
        $workflowInfo->execution = new WorkflowExecution('123', (string)$request->getID());

        $this->marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturn(new Input($workflowInfo));

        $this->router->handle($request, [], new Deferred());
        $this->assertNotNull($this->services->running->find($workflowInfo->execution->getRunID()));
    }

    public function testStartingAlreadyRunningWorkflow(): void
    {
        $request = new Request(Uuid::v4(), DummyWorkflow::class, EncodedValues::fromValues([]));

        $workflowInfo = new WorkflowInfo();
        $workflowInfo->type->name = 'DummyWorkflow';
        $workflowInfo->execution = new WorkflowExecution('123', (string)$request->getID());

        $this->marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturn(new Input($workflowInfo));

        $this->services->running->add($request);

        try {
            $this->router->handle($request, [], new Deferred());
        } catch (LogicException $exception) {
            $this->fail($exception->getMessage());
        }
    }

    public function testAlreadyRunningWorkflowIsReturned(): void
    {
        $request = new Request(Uuid::v4(), DummyWorkflow::class, EncodedValues::fromValues([]));

        $workflowInfo = new WorkflowInfo();
        $workflowInfo->type->name = 'DummyWorkflow';
        $workflowInfo->execution = new WorkflowExecution('123', (string)$request->getID());

        $this->marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturn(new Input($workflowInfo));

        $this->services->running->add($request);

        try {
            $this->router->handle($request, [], new Deferred());
        } catch (LogicException $exception) {
            $this->fail($exception->getMessage());
        }
    }
}
