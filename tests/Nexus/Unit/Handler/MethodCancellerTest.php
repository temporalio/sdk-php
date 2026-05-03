<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Handler\ClosureMethodCancellationListener;
use Temporal\Nexus\Handler\MethodCanceller;
use Temporal\Nexus\Handler\MethodCancellationListenerInterface;
use Temporal\Tests\Nexus\Support\FrozenClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MethodCanceller::class)]
final class MethodCancellerTest extends TestCase
{
    public function testNotCancelledByDefault(): void
    {
        $canceller = new MethodCanceller();

        self::assertFalse($canceller->isCancelled());
        self::assertNull($canceller->getReason());
    }

    public function testCancelSetsReason(): void
    {
        $canceller = new MethodCanceller();
        $canceller->cancel('deadline exceeded');

        self::assertTrue($canceller->isCancelled());
        self::assertSame('deadline exceeded', $canceller->getReason());
    }

    public function testCancelIsIdempotent(): void
    {
        $canceller = new MethodCanceller();
        $canceller->cancel('first');
        $canceller->cancel('second');

        self::assertSame('first', $canceller->getReason(), 'second cancel must be a no-op');
    }

    public function testListenerInvokedOnCancel(): void
    {
        $canceller = new MethodCanceller();
        $hits = 0;
        $canceller->addListener(ClosureMethodCancellationListener::fromCallable(
            static function () use (&$hits): void {
                $hits++;
            },
        ));

        $canceller->cancel('shutdown');

        self::assertSame(1, $hits);
        // Listeners must read the reason from the canceller if they need it.
        self::assertSame('shutdown', $canceller->getReason());
    }

    public function testListenerInvokedOnlyOnceAcrossDuplicateCancels(): void
    {
        $canceller = new MethodCanceller();
        $count = 0;
        $canceller->addListener(ClosureMethodCancellationListener::fromCallable(
            static function () use (&$count): void {
                $count++;
            },
        ));

        $canceller->cancel('first');
        $canceller->cancel('second');

        self::assertSame(1, $count);
    }

    public function testListenerAddedAfterCancelInvokedImmediately(): void
    {
        $canceller = new MethodCanceller();
        $canceller->cancel('gone');

        $fired = false;
        $canceller->addListener(ClosureMethodCancellationListener::fromCallable(
            static function () use (&$fired): void {
                $fired = true;
            },
        ));

        self::assertTrue($fired);
    }

    public function testRemovedListenerNotInvoked(): void
    {
        $canceller = new MethodCanceller();
        $invoked = false;

        $listener = new class ($invoked) implements MethodCancellationListenerInterface {
            public function __construct(private bool &$invoked) {}

            public function cancelled(): void
            {
                $this->invoked = true;
            }
        };

        $canceller->addListener($listener);
        $canceller->removeListener($listener);
        $canceller->cancel('irrelevant');

        self::assertFalse($invoked);
    }

    public function testDeadlineNotExpiredYet(): void
    {
        $canceller = new MethodCanceller(new \DateTimeImmutable('+1 hour'));

        self::assertFalse($canceller->isCancelled());
        self::assertNull($canceller->getReason());
    }

    public function testExpiredDeadlineAutoCancelsOnIsCancelled(): void
    {
        $canceller = new MethodCanceller(new \DateTimeImmutable('-1 second'));

        self::assertTrue($canceller->isCancelled());
        self::assertStringContainsString('deadline exceeded', (string) $canceller->getReason());
    }

    public function testExpiredDeadlineAutoCancelsOnGetReason(): void
    {
        $canceller = new MethodCanceller(new \DateTimeImmutable('-1 second'));

        // getReason() must trip the cancellation even if isCancelled() wasn't called first.
        self::assertNotNull($canceller->getReason());
        self::assertTrue($canceller->isCancelled());
    }

    public function testListenerFiresOnDeadlineTrip(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $deadline = new \DateTimeImmutable('2026-01-01T00:00:00.100Z');
        $canceller = new MethodCanceller($deadline, $clock);

        $fired = false;
        $canceller->addListener(ClosureMethodCancellationListener::fromCallable(
            static function () use (&$fired): void {
                $fired = true;
            },
        ));

        $clock->advance(new \DateInterval('PT1S'));
        self::assertTrue($canceller->isCancelled());

        self::assertTrue($fired);
        self::assertStringContainsString('deadline exceeded', (string) $canceller->getReason());
    }

    public function testExplicitCancelWinsOverDeadline(): void
    {
        $canceller = new MethodCanceller(new \DateTimeImmutable('-1 second'));

        $canceller->cancel('shutdown');

        // Although the deadline is in the past, the explicit reason recorded first wins.
        // Because checkDeadline() is a no-op once reason is set, the explicit call must be allowed to stick.
        // Note: in our impl, checkDeadline runs lazily on inspection. A canceller constructed with a
        // past deadline will have `$reason` still null until inspected. Explicit cancel() before any
        // inspection records "shutdown"; subsequent checkDeadline sees reason set and exits.
        self::assertSame('shutdown', $canceller->getReason());
    }

    public function testAddListenerOnAlreadyExpiredDeadlineInvokesImmediately(): void
    {
        $canceller = new MethodCanceller(new \DateTimeImmutable('-1 second'));

        $fired = false;
        $canceller->addListener(ClosureMethodCancellationListener::fromCallable(
            static function () use (&$fired): void {
                $fired = true;
            },
        ));

        self::assertTrue($fired, 'listener must fire synchronously when deadline already passed');
        self::assertStringContainsString('deadline exceeded', (string) $canceller->getReason());
    }

    public function testGetDeadlineReturnsProvidedValue(): void
    {
        $deadline = new \DateTimeImmutable('+5 minutes');
        $canceller = new MethodCanceller($deadline);

        self::assertSame($deadline, $canceller->getDeadline());
    }

    public function testNullDeadlineBehavesAsBefore(): void
    {
        $canceller = new MethodCanceller();

        self::assertNull($canceller->getDeadline());
        self::assertFalse($canceller->isCancelled());
    }

    public function testListenersInvokedInRegistrationOrder(): void
    {
        $canceller = new MethodCanceller();
        $order = [];
        $canceller->addListener(ClosureMethodCancellationListener::fromCallable(
            static function () use (&$order): void {
                $order[] = 'a';
            },
        ));
        $canceller->addListener(ClosureMethodCancellationListener::fromCallable(
            static function () use (&$order): void {
                $order[] = 'b';
            },
        ));

        $canceller->cancel('x');

        self::assertSame(['a', 'b'], $order);
    }
}
