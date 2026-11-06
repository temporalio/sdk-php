<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow\Process;

use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Workflow;

final class ProcessInitializationFailureTestCase extends TestCase
{
    public function testArgumentResolutionFailureStagesTerminalWorkflowFailure(): void
    {
        $decodeFailure = new \RuntimeException('cannot decode WorkflowInit argument');
        $input = $this->createStub(ValuesInterface::class);
        $input->method('count')->willReturn(1);
        $input->method('getValue')->willThrowException($decodeFailure);

        $factory = new WorkerFactoryMock(DataConverter::createDefault());
        $services = ServiceContainer::fromWorkerFactory(
            $factory,
            ExceptionInterceptor::createDefault(),
            new SimplePipelineProvider(),
            new StderrLogger(),
        );

        $reflection = new \ReflectionClass(WorkflowWithFailingInitArgumentResolution::class);
        $prototype = new WorkflowPrototype(
            'WorkflowWithFailingInitArgumentResolution',
            $reflection->getMethod('run'),
            $reflection,
        );
        $prototype->setHasInitializer(true);
        $instance = new WorkflowInstance($prototype, $reflection->newInstanceWithoutConstructor());
        $context = new WorkflowContext(
            $services,
            $services->client,
            $instance,
            new Input(args: $input),
            EncodedValues::empty(),
        );
        $process = new Process($services, 'run-id', $instance);

        $process->initAndStart($context, $instance, false);

        $commands = \iterator_to_array($factory->getQueue());

        self::assertCount(1, $commands);
        self::assertInstanceOf(CompleteWorkflow::class, $commands[0]);
        self::assertInstanceOf(InvalidArgumentException::class, $commands[0]->getFailure());
        self::assertSame($decodeFailure, $commands[0]->getFailure()?->getPrevious());
    }

    protected function tearDown(): void
    {
        Workflow::setCurrentContext(null);
    }
}

final class WorkflowWithFailingInitArgumentResolution
{
    public function __construct(int $value) {}

    public function run(int $value): void {}
}
