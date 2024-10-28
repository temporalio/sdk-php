<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Workflow;

use PHPUnit\Framework\TestCase;
use Temporal\Workflow\Mutex;

final class MutexTestCase extends TestCase
{
    public function testIsLockedLockUnlock(): void
    {
        $mutex = new Mutex();

        $this->assertFalse($mutex->isLocked());
        $mutex->lock();
        $this->assertTrue($mutex->isLocked());
        $mutex->unlock();
        $this->assertFalse($mutex->isLocked());
    }

    public function testTryLock(): void
    {
        $mutex = new Mutex();

        $this->assertTrue($mutex->tryLock());
        $this->assertFalse($mutex->tryLock());
        $mutex->unlock();
        $this->assertTrue($mutex->tryLock());
    }

    public function testLock(): void
    {
        $result = [false, false, false];

        $mutex = new Mutex();
        $this->assertTrue($mutex->tryLock());

        $mutex->lock()->then(function (Mutex $mutex) use (&$result) {
            $result[0] = true;
            $mutex->unlock();
        });
        $mutex->lock()->then(function () use (&$result) {
            $result[1] = true;
        });
        $mutex->lock()->then(function () use (&$result) {
            $result[2] = true;
        });


        $this->assertSame([false, false, false], $result);

        $mutex->unlock();
        $this->assertSame([true, true, false], $result);

        $mutex->unlock();
        $this->assertSame([true, true, true], $result);
    }
}
