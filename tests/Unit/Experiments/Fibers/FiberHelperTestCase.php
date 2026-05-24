<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Experiments\Fibers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\Exception\OutOfContextException;
use Temporal\Experiments\Fibers\FiberHelper;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Workflow\ScopeContext;

#[CoversClass(FiberHelper::class)]
final class FiberHelperTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Facade::setCurrentContext(null);
    }

    public function testIsInFiberModeReturnsFalseWhenNoContext(): void
    {
        Facade::setCurrentContext(null);

        self::assertFalse(FiberHelper::isInFiberMode());
    }

    public function testIsInFiberModeReturnsFalseWhenContextIsNotScopeContext(): void
    {
        Facade::setCurrentContext(new \stdClass());

        self::assertFalse(FiberHelper::isInFiberMode());
    }

    public function testIsInFiberModeReturnsFalseWhenScopeContextFlagFalse(): void
    {
        $context = $this->makeScopeContextStub(false);
        Facade::setCurrentContext($context);

        self::assertFalse(FiberHelper::isInFiberMode());
    }

    public function testIsInFiberModeReturnsTrueWhenScopeContextFlagTrue(): void
    {
        $context = $this->makeScopeContextStub(true);
        Facade::setCurrentContext($context);

        self::assertTrue(FiberHelper::isInFiberMode());
    }

    public function testAwaitThrowsWhenNotInContext(): void
    {
        Facade::setCurrentContext(null);
        $promise = $this->createMock(PromiseInterface::class);

        $this->expectException(OutOfContextException::class);
        $this->expectExceptionMessage(
            'FiberHelper::await() can be used only inside a Fiber-mode workflow scope.',
        );

        FiberHelper::await($promise);
    }

    public function testAwaitThrowsWhenContextIsNotScopeContext(): void
    {
        Facade::setCurrentContext(new \stdClass());
        $promise = $this->createMock(PromiseInterface::class);

        $this->expectException(OutOfContextException::class);
        $this->expectExceptionMessage(
            'FiberHelper::await() can be used only inside a Fiber-mode workflow scope.',
        );

        FiberHelper::await($promise);
    }

    public function testAwaitThrowsWhenFiberModeIsFalse(): void
    {
        Facade::setCurrentContext($this->makeScopeContextStub(false));
        $promise = $this->createMock(PromiseInterface::class);

        $this->expectException(OutOfContextException::class);
        $this->expectExceptionMessage(
            'FiberHelper::await() can be used only inside a Fiber-mode workflow scope.',
        );

        FiberHelper::await($promise);
    }

    public function testAwaitSuspendsFiberAndReturnsResumedValue(): void
    {
        $context = $this->makeScopeContextStub(true);
        $promise = $this->createMock(PromiseInterface::class);

        $fiber = new \Fiber(static function () use ($context, $promise): mixed {
            Facade::setCurrentContext($context);
            return FiberHelper::await($promise);
        });

        $suspended = $fiber->start();
        self::assertSame($promise, $suspended);

        $returned = $fiber->resume('resolved-value');
        self::assertNull($returned);
        self::assertTrue($fiber->isTerminated());
        self::assertSame('resolved-value', $fiber->getReturn());
    }

    public function testAwaitPropagatesExceptionThrownIntoFiber(): void
    {
        $context = $this->makeScopeContextStub(true);
        $promise = $this->createMock(PromiseInterface::class);

        $fiber = new \Fiber(static function () use ($context, $promise): mixed {
            Facade::setCurrentContext($context);
            return FiberHelper::await($promise);
        });

        $fiber->start();

        $thrown = null;
        try {
            $fiber->throw(new \RuntimeException('rejection-from-promise'));
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(\RuntimeException::class, $thrown);
        self::assertSame('rejection-from-promise', $thrown->getMessage());
        self::assertTrue($fiber->isTerminated());
    }

    private function makeScopeContextStub(bool $fiberMode): ScopeContext
    {
        $context = (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
        $context->setFiberMode($fiberMode);
        return $context;
    }
}
