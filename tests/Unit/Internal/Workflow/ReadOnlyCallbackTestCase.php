<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Header;
use Temporal\Exception\IllegalStateException;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Internal\Declaration\Prototype\QueryDefinition;
use Temporal\Internal\Declaration\Prototype\UpdateDefinition;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Workflow;
use Temporal\Worker\FeatureFlags;
use Temporal\Workflow\HandlerUnfinishedPolicy;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\UpdateValidatorMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

final class ReadOnlyCallbackTestCase extends TestCase
{
    private WorkerFactoryMock $factory;

    public function testAQueryHandlerCannotCreateACommand(): void
    {
        [$process, $instance] = $this->start(new QueryingWorkflow());
        $queued = $this->factory->getQueue()->count();

        $handler = $instance->getQueryDispatcher()->findQueryHandler('sendsACommand');
        self::assertNotNull($handler);

        $error = null;

        try {
            $handler(new QueryInput('sendsACommand', EncodedValues::empty(), $process->getContext()->getInfo()));
        } catch (\Throwable $e) {
            $error = $e;
        } finally {
            Workflow::setCurrentContext(null);
        }

        self::assertSame($queued, $this->factory->getQueue()->count(), 'A query handler created a command.');
        self::assertInstanceOf(IllegalStateException::class, $error);
        self::assertStringContainsString('a query handler', $error->getMessage());
    }

    public function testAQueryHandlerDoesNotOverwriteTheWorkflowTrace(): void
    {
        [$process, $instance] = $this->start(new QueryingWorkflow());
        $before = $process->getContext()->getStackTrace();

        try {
            $handler = $instance->getQueryDispatcher()->findQueryHandler('sendsACommand');
            $handler(new QueryInput('sendsACommand', EncodedValues::empty(), $process->getContext()->getInfo()));
        } catch (\Throwable) {
        } finally {
            Workflow::setCurrentContext(null);
        }

        self::assertSame($before, $process->getContext()->getStackTrace());
    }

    public function testAnUpdateValidatorCannotCreateACommand(): void
    {
        $workflow = new ValidatingWorkflow();
        [$process, $instance] = $this->start($workflow);
        $queued = $this->factory->getQueue()->count();

        $error = null;

        try {
            $validator = $instance->getUpdateDispatcher()->findValidateUpdateHandler('sendsACommand');
            self::assertNotNull($validator);
            $validator(new UpdateInput(
                'sendsACommand',
                'update-id',
                $process->getContext()->getInfo(),
                EncodedValues::empty(),
                Header::empty(),
                false,
            ));
        } catch (\Throwable $e) {
            $error = $e;
        } finally {
            Workflow::setCurrentContext(null);
        }

        self::assertSame($queued, $this->factory->getQueue()->count(), 'An update validator created a command.');
        self::assertInstanceOf(IllegalStateException::class, $error);
        self::assertStringContainsString('an update validator', $error->getMessage());
    }

    public function testTheGuardIsDisabledByItsFeatureFlag(): void
    {
        FeatureFlags::$readOnlyWorkflowCallbacks = false;

        try {
            $workflow = new ConditionSendingWorkflow();
            $this->start($workflow);

            self::assertNull($workflow->error);
            self::assertGreaterThan(0, $this->factory->getQueue()->count());
        } finally {
            FeatureFlags::$readOnlyWorkflowCallbacks = true;
        }
    }

    public function testAnAwaitConditionCannotCreateACommand(): void
    {
        $workflow = new ConditionSendingWorkflow();
        $this->start($workflow);

        self::assertSame(0, $this->factory->getQueue()->count(), 'An await condition created a command.');
        self::assertInstanceOf(IllegalStateException::class, $workflow->error);
        self::assertStringContainsString('an await condition', $workflow->error->getMessage());
    }

    public function testASideEffectCallbackCannotCreateACommand(): void
    {
        $workflow = new SideEffectSendingWorkflow();
        $this->start($workflow);

        self::assertInstanceOf(IllegalStateException::class, $workflow->error);
        self::assertStringContainsString('a side effect callback', $workflow->error->getMessage());
    }

    protected function tearDown(): void
    {
        Workflow::setCurrentContext(null);
    }

    /**
     * @return array{Process, WorkflowInstance}
     */
    private function start(object $workflow): array
    {
        $this->factory = new WorkerFactoryMock(DataConverter::createDefault());
        $services = ServiceContainer::fromWorkerFactory(
            $this->factory,
            ExceptionInterceptor::createDefault(),
            new SimplePipelineProvider(),
            new StderrLogger(),
        );

        $reflection = new \ReflectionClass($workflow);
        $prototype = new WorkflowPrototype(
            $reflection->getShortName(),
            $reflection->getMethod('handle'),
            $reflection,
        );

        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes(Workflow\UpdateMethod::class) as $attribute) {
                $name = $attribute->newInstance()->name ?? $method->getName();
                $validator = null;

                foreach ($reflection->getMethods() as $candidate) {
                    foreach ($candidate->getAttributes(UpdateValidatorMethod::class) as $validatorAttribute) {
                        $validatorAttribute->newInstance()->forUpdate === $method->getName() and $validator = $candidate;
                    }
                }

                $prototype->addUpdateHandler(new UpdateDefinition(
                    $name,
                    '',
                    HandlerUnfinishedPolicy::WarnAndAbandon,
                    'string',
                    $method,
                    $validator,
                ));
            }

            foreach ($method->getAttributes(QueryMethod::class) as $attribute) {
                $prototype->addQueryHandler(new QueryDefinition(
                    $attribute->newInstance()->name ?? $method->getName(),
                    'string',
                    $method,
                    '',
                ));
            }
        }

        $instance = new WorkflowInstance($prototype, $workflow);
        $context = new WorkflowContext(
            $services,
            $services->client,
            $instance,
            new Input(),
            EncodedValues::empty(),
        );
        $process = new Process($services, 'run-id', $instance);
        $process->initAndStart($context, $instance, false);
        $this->factory->tick();

        return [$process, $instance];
    }
}

#[WorkflowInterface]
final class QueryingWorkflow
{
    #[WorkflowMethod(name: 'QueryingWorkflow')]
    public function handle(): \Generator
    {
        yield Workflow::await(static fn(): bool => false);
    }

    #[QueryMethod(name: 'sendsACommand')]
    public function sendsACommand(): string
    {
        Workflow::timer(5);

        return 'unreachable';
    }
}

#[WorkflowInterface]
final class ValidatingWorkflow
{
    #[WorkflowMethod(name: 'ValidatingWorkflow')]
    public function handle(): \Generator
    {
        yield Workflow::await(static fn(): bool => false);
    }

    #[Workflow\UpdateMethod(name: 'sendsACommand')]
    public function sendsACommand(): string
    {
        return 'done';
    }

    #[UpdateValidatorMethod(forUpdate: 'sendsACommand')]
    public function validateSendsACommand(): void
    {
        Workflow::timer(5);
    }
}

#[WorkflowInterface]
final class ConditionSendingWorkflow
{
    public ?\Throwable $error = null;

    #[WorkflowMethod(name: 'ConditionSendingWorkflow')]
    public function handle(): \Generator
    {
        yield Workflow::await(function (): bool {
            try {
                Workflow::timer(5);
            } catch (\Throwable $e) {
                $this->error ??= $e;
            }

            return false;
        });
    }
}

#[WorkflowInterface]
final class SideEffectSendingWorkflow
{
    public ?\Throwable $error = null;

    #[WorkflowMethod(name: 'SideEffectSendingWorkflow')]
    public function handle(): \Generator
    {
        yield Workflow::sideEffect(function (): int {
            try {
                Workflow::timer(5);
            } catch (\Throwable $e) {
                $this->error ??= $e;
            }

            return 1;
        });

        yield Workflow::await(static fn(): bool => false);
    }
}
