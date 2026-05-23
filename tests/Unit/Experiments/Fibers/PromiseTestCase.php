<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Experiments\Fibers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\Exception\OutOfContextException;
use Temporal\Experiments\Fibers\Promise;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Workflow\ScopeContext;

#[CoversClass(Promise::class)]
final class PromiseTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Facade::setCurrentContext(null);
    }

    public function testResolveReturnsPromiseWithoutSuspending(): void
    {
        Facade::setCurrentContext(null);

        $result = Promise::resolve(42);

        self::assertInstanceOf(PromiseInterface::class, $result);
    }

    public function testRejectReturnsPromiseWithoutSuspending(): void
    {
        Facade::setCurrentContext(null);

        $result = Promise::reject(new \RuntimeException('test'));

        self::assertInstanceOf(PromiseInterface::class, $result);
    }

    public function testAllThrowsOutsideFiberMode(): void
    {
        Facade::setCurrentContext(null);

        $this->expectException(OutOfContextException::class);
        Promise::all([Promise::resolve(1), Promise::resolve(2)]);
    }

    public function testAnyThrowsOutsideFiberMode(): void
    {
        Facade::setCurrentContext(null);

        $this->expectException(OutOfContextException::class);
        Promise::any([Promise::resolve(1)]);
    }

    public function testSomeThrowsOutsideFiberMode(): void
    {
        Facade::setCurrentContext(null);

        $this->expectException(OutOfContextException::class);
        Promise::some([Promise::resolve(1)], 1);
    }

    public function testRaceThrowsOutsideFiberMode(): void
    {
        Facade::setCurrentContext(null);

        $this->expectException(OutOfContextException::class);
        Promise::race([Promise::resolve(1)]);
    }

    public function testMapThrowsOutsideFiberMode(): void
    {
        Facade::setCurrentContext(null);

        $this->expectException(OutOfContextException::class);
        Promise::map([Promise::resolve(1)], static fn($v) => $v);
    }

    public function testReduceThrowsOutsideFiberMode(): void
    {
        Facade::setCurrentContext(null);

        $this->expectException(OutOfContextException::class);
        Promise::reduce([Promise::resolve(1)], static fn($acc, $v) => $acc + $v, 0);
    }

    public function testAllSuspendsInsideFiber(): void
    {
        $context = (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
        $context->setFiberMode(true);

        $fiber = new \Fiber(static function () use ($context): mixed {
            Facade::setCurrentContext($context);
            return Promise::all([Promise::resolve(1), Promise::resolve(2)]);
        });

        $suspended = $fiber->start();
        self::assertInstanceOf(PromiseInterface::class, $suspended);

        $fiber->resume([1, 2]);
        self::assertSame([1, 2], $fiber->getReturn());
    }
}
