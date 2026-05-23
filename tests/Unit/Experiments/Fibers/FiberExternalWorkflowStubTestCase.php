<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Experiments\Fibers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\Exception\OutOfContextException;
use Temporal\Experiments\Fibers\FiberExternalWorkflowStub;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Workflow\ExternalWorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

#[CoversClass(FiberExternalWorkflowStub::class)]
final class FiberExternalWorkflowStubTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Facade::setCurrentContext(null);
    }

    public function testGetExecutionIsSyncPassthrough(): void
    {
        $execution = new WorkflowExecution('wf-id', 'run-id');
        $inner = $this->createMock(ExternalWorkflowStubInterface::class);
        $inner->expects(self::once())->method('getExecution')->willReturn($execution);

        self::assertSame($execution, (new FiberExternalWorkflowStub($inner))->getExecution());
    }

    public function testSignalAsyncReturnsRawPromise(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ExternalWorkflowStubInterface::class);
        $inner->expects(self::once())->method('signal')->with('go', [])->willReturn($promise);

        Facade::setCurrentContext(null);

        self::assertSame($promise, (new FiberExternalWorkflowStub($inner))->signalAsync('go'));
    }

    public function testCancelAsyncReturnsRawPromise(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ExternalWorkflowStubInterface::class);
        $inner->expects(self::once())->method('cancel')->willReturn($promise);

        Facade::setCurrentContext(null);

        self::assertSame($promise, (new FiberExternalWorkflowStub($inner))->cancelAsync());
    }

    public function testSignalThrowsOutsideFiber(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ExternalWorkflowStubInterface::class);
        $inner->method('signal')->willReturn($promise);

        Facade::setCurrentContext(null);

        $this->expectException(OutOfContextException::class);
        (new FiberExternalWorkflowStub($inner))->signal('go');
    }

    public function testCancelSuspendsInsideFiber(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ExternalWorkflowStubInterface::class);
        $inner->expects(self::once())->method('cancel')->willReturn($promise);

        $context = (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
        $context->setFiberMode(true);

        $stub = new FiberExternalWorkflowStub($inner);

        $fiber = new \Fiber(static function () use ($context, $stub): void {
            Facade::setCurrentContext($context);
            $stub->cancel();
        });

        $suspended = $fiber->start();
        self::assertSame($promise, $suspended);

        $fiber->resume(null);
        self::assertTrue($fiber->isTerminated());
    }
}
