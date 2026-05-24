<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Experiments\Fibers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\Experiments\Fibers\Mutex;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Workflow\Mutex as BaseMutex;

#[CoversClass(Mutex::class)]
final class MutexTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Facade::setCurrentContext(null);
    }

    public function testInitialStateIsUnlocked(): void
    {
        $mutex = new Mutex();
        self::assertFalse($mutex->isLocked());
    }

    public function testTryLockReturnsTrueOnFirstCallAndFalseOnSubsequent(): void
    {
        $mutex = new Mutex();
        self::assertTrue($mutex->tryLock());
        self::assertTrue($mutex->isLocked());
        self::assertFalse($mutex->tryLock());
    }

    public function testUnlockClearsLockedFlag(): void
    {
        $mutex = new Mutex();
        $mutex->tryLock();
        self::assertTrue($mutex->isLocked());

        $mutex->unlock();
        self::assertFalse($mutex->isLocked());
    }

    public function testGetInnerExposesBaseMutex(): void
    {
        $mutex = new Mutex();
        $inner = $mutex->getInner();

        self::assertInstanceOf(BaseMutex::class, $inner);
        $inner->tryLock();
        self::assertTrue($mutex->isLocked());
    }

    public function testLockOutsideFiberReturnsPromise(): void
    {
        Facade::setCurrentContext(null);
        $mutex = new Mutex();

        $result = $mutex->lock();

        self::assertInstanceOf(PromiseInterface::class, $result);
        self::assertTrue($mutex->isLocked());
    }

    public function testLockInsideFiberSuspendsAndReturnsResumedValue(): void
    {
        $context = (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
        $context->setFiberMode(true);

        $mutex = new Mutex();

        $fiber = new \Fiber(static function () use ($context, $mutex): mixed {
            Facade::setCurrentContext($context);
            return $mutex->lock();
        });

        $suspended = $fiber->start();
        self::assertInstanceOf(PromiseInterface::class, $suspended);

        $fiber->resume($mutex->getInner());
        self::assertTrue($fiber->isTerminated());
        self::assertSame($mutex->getInner(), $fiber->getReturn());
    }
}
