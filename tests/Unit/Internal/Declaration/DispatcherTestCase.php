<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Declaration\Dispatcher\Dispatcher;

#[CoversClass(Dispatcher::class)]
final class DispatcherTestCase extends TestCase
{
    public function testStaticClosureReturningNullIsInvokedOnce(): void
    {
        $calls = 0;
        $handler = static function () use (&$calls): void {
            ++$calls;
        };

        $dispatcher = new Dispatcher(new \ReflectionFunction($handler));

        self::assertNull($dispatcher->dispatch($this, []));
        self::assertSame(1, $calls);
    }
}
