<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Experiments\Fibers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\Exception\OutOfContextException;
use Temporal\Experiments\Fibers\FiberChildWorkflowStub;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

#[CoversClass(FiberChildWorkflowStub::class)]
final class FiberChildWorkflowStubTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Facade::setCurrentContext(null);
    }

    public function testGetChildWorkflowTypeDelegatesToInner(): void
    {
        $inner = $this->createMock(ChildWorkflowStubInterface::class);
        $inner->expects(self::once())->method('getChildWorkflowType')->willReturn('MyChild');

        self::assertSame('MyChild', (new FiberChildWorkflowStub($inner))->getChildWorkflowType());
    }

    public function testGetOptionsDelegatesToInner(): void
    {
        $options = ChildWorkflowOptions::new();
        $inner = $this->createMock(ChildWorkflowStubInterface::class);
        $inner->expects(self::once())->method('getOptions')->willReturn($options);

        self::assertSame($options, (new FiberChildWorkflowStub($inner))->getOptions());
    }

    public function testStartAsyncReturnsRawPromise(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ChildWorkflowStubInterface::class);
        $inner->expects(self::once())->method('start')->with('a')->willReturn($promise);

        Facade::setCurrentContext(null);

        self::assertSame($promise, (new FiberChildWorkflowStub($inner))->startAsync('a'));
    }

    public function testSignalSuspendsInsideFiber(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ChildWorkflowStubInterface::class);
        $inner->expects(self::once())->method('signal')->with('go', [])->willReturn($promise);

        $context = (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
        $context->setFiberMode(true);

        $stub = new FiberChildWorkflowStub($inner);

        $fiber = new \Fiber(static function () use ($context, $stub): void {
            Facade::setCurrentContext($context);
            $stub->signal('go');
        });

        $suspended = $fiber->start();
        self::assertSame($promise, $suspended);

        $fiber->resume(null);
        self::assertTrue($fiber->isTerminated());
    }

    public function testStartThrowsOutsideFiber(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ChildWorkflowStubInterface::class);
        $inner->method('start')->willReturn($promise);

        Facade::setCurrentContext(null);

        $this->expectException(OutOfContextException::class);
        (new FiberChildWorkflowStub($inner))->start();
    }

    public function testGetExecutionSuspendsAndReturnsExecution(): void
    {
        $execution = new WorkflowExecution('wf-id', 'run-id');
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ChildWorkflowStubInterface::class);
        $inner->expects(self::once())->method('getExecution')->willReturn($promise);

        $context = (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
        $context->setFiberMode(true);

        $stub = new FiberChildWorkflowStub($inner);

        $fiber = new \Fiber(static function () use ($context, $stub): WorkflowExecution {
            Facade::setCurrentContext($context);
            return $stub->getExecution();
        });

        $fiber->start();
        $fiber->resume($execution);
        self::assertSame($execution, $fiber->getReturn());
    }
}
