<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Handler\ClosureMethodCancellationListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClosureMethodCancellationListener::class)]
final class ClosureMethodCancellationListenerTest extends TestCase
{
    public function testCancelledInvokesWrappedCallable(): void
    {
        $calls = 0;
        $listener = ClosureMethodCancellationListener::fromCallable(static function () use (&$calls): void {
            $calls++;
        });

        $listener->cancelled();
        $listener->cancelled();

        self::assertSame(2, $calls);
    }
}
