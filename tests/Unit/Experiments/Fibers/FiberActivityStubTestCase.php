<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Experiments\Fibers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\Exception\OutOfContextException;
use Temporal\Experiments\Fibers\FiberActivityStub;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Workflow\ActivityStubInterface;

#[CoversClass(FiberActivityStub::class)]
final class FiberActivityStubTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Facade::setCurrentContext(null);
    }

    public function testGetOptionsDelegatesToInner(): void
    {
        $options = ActivityOptions::new();
        $inner = $this->createMock(ActivityStubInterface::class);
        $inner->expects(self::once())->method('getOptions')->willReturn($options);

        $stub = new FiberActivityStub($inner);

        self::assertSame($options, $stub->getOptions());
    }

    public function testExecuteAsyncReturnsRawPromise(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ActivityStubInterface::class);
        $inner->expects(self::once())
            ->method('execute')
            ->with('my-activity', ['arg'], null, false)
            ->willReturn($promise);

        Facade::setCurrentContext(null);
        $stub = new FiberActivityStub($inner);

        self::assertSame($promise, $stub->executeAsync('my-activity', ['arg']));
    }

    public function testExecuteAsyncForwardsReturnTypeAndLocalActivityFlag(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ActivityStubInterface::class);
        $inner->expects(self::once())
            ->method('execute')
            ->with('my-activity', ['arg'], 'string', true)
            ->willReturn($promise);

        Facade::setCurrentContext(null);
        $stub = new FiberActivityStub($inner);

        self::assertSame($promise, $stub->executeAsync('my-activity', ['arg'], 'string', true));
    }

    public function testExecuteForwardsAllArgumentsToInner(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ActivityStubInterface::class);
        $inner->expects(self::once())
            ->method('execute')
            ->with('act', ['payload'], 'string', true)
            ->willReturn($promise);

        $context = (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
        $context->setFiberMode(true);

        $stub = new FiberActivityStub($inner);

        $fiber = new \Fiber(static function () use ($context, $stub): mixed {
            Facade::setCurrentContext($context);
            return $stub->execute('act', ['payload'], 'string', true);
        });

        $suspended = $fiber->start();
        self::assertSame($promise, $suspended);

        $fiber->resume('done');
        self::assertSame('done', $fiber->getReturn());
    }

    public function testExecuteThrowsOutsideFiber(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ActivityStubInterface::class);
        $inner->method('execute')->willReturn($promise);

        Facade::setCurrentContext(null);
        $stub = new FiberActivityStub($inner);

        $this->expectException(OutOfContextException::class);
        $stub->execute('my-activity');
    }

    public function testExecuteSuspendsInsideFiber(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ActivityStubInterface::class);
        $inner->method('execute')->willReturn($promise);

        $context = (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
        $context->setFiberMode(true);

        $stub = new FiberActivityStub($inner);

        $fiber = new \Fiber(static function () use ($context, $stub): mixed {
            Facade::setCurrentContext($context);
            return $stub->execute('my-activity');
        });

        $suspended = $fiber->start();
        self::assertSame($promise, $suspended);

        $fiber->resume('result');
        self::assertSame('result', $fiber->getReturn());
    }

    public function testExecutePropagatesExceptionThrownIntoFiber(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = $this->createMock(ActivityStubInterface::class);
        $inner->method('execute')->willReturn($promise);

        $context = (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
        $context->setFiberMode(true);

        $stub = new FiberActivityStub($inner);

        $fiber = new \Fiber(static function () use ($context, $stub): mixed {
            Facade::setCurrentContext($context);
            return $stub->execute('my-activity');
        });

        $fiber->start();

        $thrown = null;
        try {
            $fiber->throw(new \RuntimeException('activity-failed'));
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(\RuntimeException::class, $thrown);
        self::assertSame('activity-failed', $thrown->getMessage());
        self::assertTrue($fiber->isTerminated());
    }
}
