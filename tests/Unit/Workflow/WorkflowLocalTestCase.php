<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Workflow;

use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Exception\OutOfContextException;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowLocal;

final class WorkflowLocalTestCase extends TestCase
{
    public function testEachWorkflowRunSeesItsOwnValue(): void
    {
        $local = new WorkflowLocal();
        $first = $this->createContext();
        $second = $this->createContext();

        Workflow::setCurrentContext($first);
        $local->set('first');
        Workflow::setCurrentContext($second);
        self::assertNull($local->get());
        $local->set('second');

        Workflow::setCurrentContext($first);
        self::assertSame('first', $local->get());
        Workflow::setCurrentContext($second);
        self::assertSame('second', $local->get());
    }

    public function testValueIsSharedBetweenContextsOfOneRun(): void
    {
        $local = new WorkflowLocal();
        $context = $this->createContext();

        Workflow::setCurrentContext($context);
        $local->set('value');
        Workflow::setCurrentContext($context->withInput(new Input()));

        self::assertSame('value', $local->get());
    }

    public function testInitialValueIsCreatedOncePerRun(): void
    {
        $calls = 0;
        $local = new WorkflowLocal(static function () use (&$calls): int {
            return ++$calls;
        });

        Workflow::setCurrentContext($this->createContext());
        self::assertSame(1, $local->get());
        self::assertSame(1, $local->get());

        Workflow::setCurrentContext($this->createContext());
        self::assertSame(2, $local->get());
    }

    public function testSetNullIsNotReplacedByInitialValue(): void
    {
        $local = new WorkflowLocal(static fn(): string => 'initial');

        Workflow::setCurrentContext($this->createContext());
        $local->set(null);

        self::assertNull($local->get());
    }

    public function testValueIsReleasedWhenWorkflowRunIsDestroyed(): void
    {
        $local = new WorkflowLocal();
        $context = $this->createContext();
        $value = new \stdClass();
        $reference = \WeakReference::create($value);

        Workflow::setCurrentContext($context);
        $local->set($value);
        Workflow::setCurrentContext(null);
        unset($value);

        $context->destroy();
        unset($context);
        \gc_collect_cycles();

        self::assertNull($reference->get());
    }

    public function testOutsideOfWorkflowContextThrows(): void
    {
        $this->expectException(OutOfContextException::class);
        $this->expectExceptionMessage('The Workflow facade can be used only inside workflow code.');

        (new WorkflowLocal())->get();
    }

    protected function tearDown(): void
    {
        Workflow::setCurrentContext(null);
    }

    private function createContext(): WorkflowContext
    {
        $services = ServiceContainer::fromWorkerFactory(
            new WorkerFactoryMock(DataConverter::createDefault()),
            ExceptionInterceptor::createDefault(),
            new SimplePipelineProvider(),
            new StderrLogger(),
        );
        $workflow = new LocalWorkflow();
        $reflection = new \ReflectionClass($workflow);
        $instance = new WorkflowInstance(
            new WorkflowPrototype('LocalWorkflow', $reflection->getMethod('handle'), $reflection),
            $workflow,
        );

        return new WorkflowContext($services, $services->client, $instance, new Input(), EncodedValues::empty());
    }
}

final class LocalWorkflow
{
    public function handle(): void {}
}
