<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Experiments\Fibers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testResolveReturnsPromiseAndPreservesValue(): void
    {
        Facade::setCurrentContext(null);

        $result = Promise::resolve(42);

        self::assertInstanceOf(PromiseInterface::class, $result);

        $seen = null;
        $result->then(static function ($value) use (&$seen): void {
            $seen = $value;
        });
        self::assertSame(42, $seen);
    }

    public function testRejectReturnsPromiseAndPreservesReason(): void
    {
        Facade::setCurrentContext(null);

        $reason = new \RuntimeException('test');
        $result = Promise::reject($reason);

        self::assertInstanceOf(PromiseInterface::class, $result);

        $seen = null;
        $result->then(null, static function ($value) use (&$seen): void {
            $seen = $value;
        });
        self::assertSame($reason, $seen);
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

    /**
     * @param \Closure(): mixed $call
     */
    #[DataProvider('provideCombinatorCalls')]
    public function testCombinatorSuspendsInsideFiber(string $name, \Closure $call, mixed $resumedValue): void
    {
        $context = (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
        $context->setFiberMode(true);

        $fiber = new \Fiber(static function () use ($context, $call): mixed {
            Facade::setCurrentContext($context);
            return $call();
        });

        $suspended = $fiber->start();
        self::assertInstanceOf(
            PromiseInterface::class,
            $suspended,
            "Combinator '{$name}' must suspend the Fiber with a PromiseInterface",
        );

        $fiber->resume($resumedValue);
        self::assertTrue($fiber->isTerminated());
        self::assertSame($resumedValue, $fiber->getReturn());
    }

    public static function provideCombinatorCalls(): iterable
    {
        yield 'all' => [
            'all',
            static fn(): mixed => Promise::all([Promise::resolve(1), Promise::resolve(2)]),
            [1, 2],
        ];
        yield 'any' => [
            'any',
            static fn(): mixed => Promise::any([Promise::resolve(1), Promise::resolve(2)]),
            1,
        ];
        yield 'some' => [
            'some',
            static fn(): mixed => Promise::some([Promise::resolve(1), Promise::resolve(2)], 1),
            [1],
        ];
        yield 'race' => [
            'race',
            static fn(): mixed => Promise::race([Promise::resolve(1), Promise::resolve(2)]),
            1,
        ];
        yield 'map' => [
            'map',
            static fn(): mixed => Promise::map([Promise::resolve(1)], static fn($v) => $v * 2),
            [2],
        ];
        yield 'reduce' => [
            'reduce',
            static fn(): mixed => Promise::reduce([Promise::resolve(1), Promise::resolve(2)], static fn($acc, $v) => $acc + $v, 0),
            3,
        ];
    }
}
