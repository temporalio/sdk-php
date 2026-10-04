<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Support\Facade;

#[CoversClass(Facade::class)]
final class FiberContextTestCase extends TestCase
{
    protected function setUp(): void
    {
        \Closure::bind(static function (): void {
            Facade::$fiberCtx = null;
        }, null, Facade::class)();
    }

    protected function tearDown(): void
    {
        Facade::setCurrentContext(null);
    }

    public function testIsolatedFibersKeepTheirOwnContext(): void
    {
        $first = new \stdClass();
        $second = new \stdClass();
        $seen = [];
        $a = new \Fiber(static function () use ($first, &$seen): void {
            Facade::setCurrentContext($first);
            \Fiber::suspend();
            $seen['a'] = Facade::getCurrentContext();
        });
        $b = new \Fiber(static function () use ($second, &$seen): void {
            Facade::setCurrentContext($second);
            \Fiber::suspend();
            $seen['b'] = Facade::getCurrentContext();
        });
        Facade::isolateFiber($a);
        Facade::isolateFiber($b);

        $a->start();
        $b->start();
        $a->resume();
        $b->resume();

        $this->assertSame($first, $seen['a']);
        $this->assertSame($second, $seen['b']);
        $this->assertNull(Facade::getCurrentContext());
    }

    public function testWithoutIsolatedFibersEveryFiberSharesTheGlobalContext(): void
    {
        $global = new \stdClass();
        $seen = null;

        (new \Fiber(static function () use ($global, &$seen): void {
            Facade::setCurrentContext($global);
            $seen = Facade::getCurrentContext();
        }))->start();

        $this->assertSame($global, $seen);
        $this->assertSame($global, Facade::getCurrentContext());
    }

    public function testFiberThatIsNotIsolatedSharesTheGlobalContext(): void
    {
        $isolated = new \Fiber(static fn() => null);
        Facade::isolateFiber($isolated);
        $global = new \stdClass();
        Facade::setCurrentContext($global);
        $seen = null;

        (new \Fiber(static function () use (&$seen): void {
            $seen = Facade::getCurrentContext();
        }))->start();

        $this->assertSame($global, $seen);
    }

    public function testUsingContextInIsolatedFiberRestoresTheFiberContext(): void
    {
        $global = new \stdClass();
        $fiberContext = new \stdClass();
        $temporary = new \stdClass();
        Facade::setCurrentContext($global);
        $seen = [];
        $fiber = new \Fiber(static function () use ($fiberContext, $temporary, &$seen): void {
            Facade::setCurrentContext($fiberContext);
            $seen['inside'] = Facade::usingContext($temporary, static fn(): ?object => Facade::getCurrentContext());
            $seen['after'] = Facade::getCurrentContext();
        });
        Facade::isolateFiber($fiber);

        $fiber->start();

        $this->assertSame($temporary, $seen['inside']);
        $this->assertSame($fiberContext, $seen['after']);
        $this->assertSame($global, Facade::getCurrentContext());
    }
}
