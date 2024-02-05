<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Support\GarbageCollector;

#[CoversClass(\Temporal\Internal\Support\GarbageCollector::class)]
final class GarbageCollectorTestCase extends TestCase
{
    #[DataProvider('provideCheck')]
    public function testCheckTicks(int $counter, int $iterations, bool $result): void
    {
        $gc = new GarbageCollector($counter, 3600);

        for ($i = 1; $i < $iterations; ++$i) {
            $this->assertFalse($gc->check());
        }

        $this->assertSame($result, $gc->check());
    }

    public function testCheckOverticked(): void
    {
        $gc = new GarbageCollector(5, 3600);

        $this->assertFalse($gc->check());
        $this->assertFalse($gc->check());
        $this->assertFalse($gc->check());
        $this->assertFalse($gc->check());
        $this->assertTrue($gc->check());
        $this->assertTrue($gc->check());
        $this->assertTrue($gc->check());
        $this->assertTrue($gc->check());
    }

    public function testCheckTimeout(): void
    {
        $gc = new GarbageCollector(100, 2);

        $this->assertFalse($gc->check());
    }

    public function testCheckTriggerTimeout(): void
    {
        $gc = new GarbageCollector(100, 1, \time() - 2);

        $this->assertTrue($gc->check());
    }

    public static function provideCheck(): iterable
    {
        yield [1, 1, true];
        yield [2, 1, false];
        yield [3, 2, false];
        yield [10, 9, false];
        yield [10, 10, true];
    }
}
