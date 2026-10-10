<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\Prototype\QueryDefinition;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Router\InvokeQuery;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowExecution;

#[CoversClass(InvokeQuery::class)]
#[CoversClass(WorkflowContext::class)]
final class FailureSerializationContextTestCase extends TestCase
{
    private WorkerFactoryMock $factory;
    private ServiceContainer $services;

    public function testQueryFailureBehindPlainExceptionIsBoundToTheWorkflowContext(): void
    {
        $this->startProcess();
        $resolver = new Deferred();
        $error = null;
        $resolver->promise()->then(null, static function (\Throwable $e) use (&$error): void {
            $error = $e;
        });

        (new InvokeQuery($this->services->running, $this->factory))->handle(
            new ServerRequest(
                name: 'InvokeQuery',
                info: new TickInfo(new \DateTimeImmutable()),
                options: ['name' => 'fail'],
                payloads: EncodedValues::empty(),
                id: 'run-id',
            ),
            [],
            $resolver,
        );
        $this->factory->tick();

        self::assertInstanceOf(\RuntimeException::class, $error);
        self::assertSame('query wrapper', $error->getMessage());
        $failure = $error->getPrevious();
        self::assertInstanceOf(ApplicationFailure::class, $failure);
        self::assertEquals(
            new WorkflowSerializationContext('default', 'workflow-id'),
            $failure->getDetails()->getSerializationContext(),
        );
    }

    public function testPanicFailureBehindPlainExceptionIsBoundToTheWorkflowContext(): void
    {
        $this->factory = new WorkerFactoryMock(DataConverter::createDefault());
        $this->services = $this->createServices();
        $input = new Input();
        $input->info->execution = new WorkflowExecution('workflow-id', 'run-id');
        $context = new WorkflowContext(
            $this->services,
            $this->createMock(ClientInterface::class),
            $this->createInstance(),
            $input,
        );
        $context->setReadonly(false);
        $failure = new ApplicationFailure('inner', 'Inner', true, EncodedValues::fromValues(['detail']));

        $context->panic(new \RuntimeException('panic wrapper', 0, $failure));

        self::assertEquals(
            new WorkflowSerializationContext('default', 'workflow-id'),
            $failure->getDetails()->getSerializationContext(),
        );
    }

    protected function tearDown(): void
    {
        Workflow::setCurrentContext(null);

        parent::tearDown();
    }

    private function startProcess(): void
    {
        $this->factory = new WorkerFactoryMock(DataConverter::createDefault());
        $this->services = $this->createServices();
        $instance = $this->createInstance();
        $input = new Input();
        $input->info->execution = new WorkflowExecution('workflow-id', 'run-id');
        $context = new WorkflowContext($this->services, $this->services->client, $instance, $input);
        $process = new Process($this->services, 'run-id', $instance);
        $this->services->running->add($process);
        $process->initAndStart($context, $instance, false);
        $this->factory->tick();
    }

    private function createServices(): ServiceContainer
    {
        return ServiceContainer::fromWorkerFactory(
            $this->factory,
            ExceptionInterceptor::createDefault(),
            new SimplePipelineProvider(),
            new StderrLogger(),
        );
    }

    private function createInstance(): WorkflowInstance
    {
        $workflow = new FailingQueryWorkflow();
        $reflection = new \ReflectionClass($workflow);
        $prototype = new WorkflowPrototype('FailingQuery', $reflection->getMethod('handle'), $reflection);
        $prototype->addQueryHandler(new QueryDefinition('fail', 'string', $reflection->getMethod('fail'), ''));

        return new WorkflowInstance($prototype, $workflow);
    }
}

final class FailingQueryWorkflow
{
    public function handle(): \Generator
    {
        yield Workflow::await(static fn(): bool => false);
    }

    public function fail(): string
    {
        throw new \RuntimeException(
            'query wrapper',
            0,
            new ApplicationFailure('inner', 'Inner', true, EncodedValues::fromValues(['detail'])),
        );
    }
}
