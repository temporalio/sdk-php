<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Router;

use Psr\Log\NullLogger;
use React\Promise\Deferred;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\Destroyable;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Internal\Declaration\WorkflowInstance\QueryDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\SignalDispatcher;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\Repository\Identifiable;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Router\StartWorkflow;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Framework\Requests\StartWorkflow as Request;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInfo;

final class StartWorkflowTestCase extends AbstractUnit
{
    private ServiceContainer $services;
    private StartWorkflow $router;
    private WorkflowContext $workflowContext;
    private MarshallerInterface $marshaller;

    public function testWorkflowIsStartedAndRunning(): void
    {
        $request = new Request($runId = Uuid::v4(), DummyWorkflow::class, EncodedValues::fromValues([]));

        $workflowInfo = new WorkflowInfo();
        $workflowInfo->type->name = 'DummyWorkflow';
        $workflowInfo->execution = new WorkflowExecution('123', (string) $request->getID());

        $this->marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturn(new Input($workflowInfo));

        $this->router->handle($request, [], new Deferred());
        $this->assertNotNull($this->services->running->find($runId));
        $this->assertNotNull($this->services->running->find($workflowInfo->execution->getRunID()));
    }

    public function testRequestRunId(): void
    {
        $request = new Request($runId = Uuid::v4(), DummyWorkflow::class, EncodedValues::fromValues([]));

        $this->assertSame($runId, $request->getID());
    }

    public function testStartingAlreadyRunningWorkflow(): void
    {
        $request = new Request(Uuid::v4(), DummyWorkflow::class, EncodedValues::fromValues([]));

        $workflowInfo = new WorkflowInfo();
        $workflowInfo->type->name = 'DummyWorkflow';
        $workflowInfo->execution = new WorkflowExecution('123', (string) $request->getID());

        $this->marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturn(new Input($workflowInfo));

        $identify = $this->createIdentifiable($request->getID());

        $this->services->running->add($identify);

        try {
            $this->router->handle($request, [], new Deferred());
        } catch (\LogicException $exception) {
            $this->fail($exception->getMessage());
        }
    }

    public function testAlreadyRunningWorkflowIsReturned(): void
    {
        $request = new Request(Uuid::v4(), DummyWorkflow::class, EncodedValues::fromValues([]));

        $workflowInfo = new WorkflowInfo();
        $workflowInfo->type->name = 'DummyWorkflow';
        $workflowInfo->execution = new WorkflowExecution('123', (string) $request->getID());

        $this->marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturn(new Input($workflowInfo));

        $identify = $this->createIdentifiable($request->getID());
        $this->services->running->add($identify);

        try {
            $this->router->handle($request, [], new Deferred());
        } catch (\LogicException $exception) {
            $this->fail($exception->getMessage());
        }
    }

    protected function setUp(): void
    {
        $workflow = new \stdClass();
        $pp = new SimplePipelineProvider();
        $dataConverter = $this->createMock(DataConverterInterface::class);
        $this->marshaller = $this->createMock(MarshallerInterface::class);
        $wfInstance = $this->createMockForIntersectionOfInterfaces([WorkflowInstanceInterface::class, Destroyable::class]);
        $wfInstance->method('getQueryDispatcher')
            ->willReturn(new QueryDispatcher($pp->getPipeline(WorkflowInboundCallsInterceptor::class), $workflow));
        $wfInstance->method('getSignalDispatcher')
            ->willReturn(new SignalDispatcher($pp->getPipeline(WorkflowInboundCallsInterceptor::class), $workflow));

        $this->services = new ServiceContainer(
            $this->createMock(LoopInterface::class),
            $this->createMock(EnvironmentInterface::class),
            $this->createMock(ClientInterface::class),
            $this->createMock(ReaderInterface::class),
            $this->createMock(QueueInterface::class),
            $this->marshaller,
            $dataConverter,
            $this->createMock(ExceptionInterceptorInterface::class),
            new SimplePipelineProvider(),
            new NullLogger(),
        );
        $workflowReader = new WorkflowReader(new SelectiveReader([new AnnotationReader(), new AttributeReader()]));
        $this->services->workflows->add($workflowReader->fromClass(DummyWorkflow::class));
        $this->router = new StartWorkflow($this->services);
        $this->workflowContext = new WorkflowContext(
            $this->services,
            $this->services->client->fork(),
            $wfInstance,
            new Input(),
            EncodedValues::empty(),
        );

        parent::setUp();
    }

    private function createIdentifiable(string $id): Identifiable
    {
        return new class($id) implements Identifiable {
            public function __construct(
                private string $id,
            ) {}

            public function getID(): string
            {
                return $this->id;
            }
        };
    }
}
