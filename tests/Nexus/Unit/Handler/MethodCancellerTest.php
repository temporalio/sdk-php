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
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MethodCanceller::class)]
final class MethodCancellerTest extends TestCase
{
    private EnvironmentInterface $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = new Environment();
    }

    public function testNotCancelledByDefault(): void
    {
        $canceller = new MethodCanceller($this->env);

        self::assertFalse($canceller->isCancelled());
        self::assertNull($canceller->getReason());
    }

    public function testCancelSetsReason(): void
    {
        $canceller = new MethodCanceller($this->env);
        $canceller->cancel('deadline exceeded');

        self::assertTrue($canceller->isCancelled());
        self::assertSame('deadline exceeded', $canceller->getReason());
    }

    public function testCancelIsIdempotent(): void
    {
        $canceller = new MethodCanceller($this->env);
        $canceller->cancel('first');
        $canceller->cancel('second');

        self::assertSame('first', $canceller->getReason(), 'second cancel must be a no-op');
    }

    public function testListenerInvokedOnCancel(): void
    {
        $canceller = new MethodCanceller($this->env);
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
        $canceller = new MethodCanceller($this->env);
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
        $canceller = new MethodCanceller($this->env);
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
        $canceller = new MethodCanceller($this->env);
        $invoked = false;

        $listener = new class($invoked) implements MethodCancellationListenerInterface {
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
        $canceller = new MethodCanceller($this->env, new \DateTimeImmutable('+1 hour'));

        self::assertFalse($canceller->isCancelled());
        self::assertNull($canceller->getReason());
    }

    public function testExpiredDeadlineAutoCancelsOnIsCancelled(): void
    {
        $canceller = new MethodCanceller($this->env, new \DateTimeImmutable('-1 second'));

        self::assertTrue($canceller->isCancelled());
        self::assertStringContainsString('deadline exceeded', (string) $canceller->getReason());
    }

    public function testExpiredDeadlineAutoCancelsOnGetReason(): void
    {
        $canceller = new MethodCanceller($this->env, new \DateTimeImmutable('-1 second'));

        // getReason() must trip the cancellation even if isCancelled() wasn't called first.
        self::assertNotNull($canceller->getReason());
        self::assertTrue($canceller->isCancelled());
    }

    public function testListenerFiresOnDeadlineTrip(): void
    {
        $this->env->update(new TickInfo(time: new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        $deadline = new \DateTimeImmutable('2026-01-01T00:00:00.100Z');
        $canceller = new MethodCanceller($this->env, $deadline);

        $fired = false;
        $canceller->addListener(ClosureMethodCancellationListener::fromCallable(
            static function () use (&$fired): void {
                $fired = true;
            },
        ));
        self::assertFalse($canceller->isCancelled());
        self::assertFalse($fired);

        $this->env->update(new TickInfo(time: new \DateTimeImmutable('2026-01-01T00:00:01Z')));
        self::assertTrue($canceller->isCancelled());

        self::assertTrue($fired);
        self::assertStringContainsString('deadline exceeded', (string) $canceller->getReason());
    }

    public function testExplicitCancelWinsOverDeadline(): void
    {
        $canceller = new MethodCanceller($this->env, new \DateTimeImmutable('-1 second'));

        $canceller->cancel('shutdown');

        // Explicit cancel() before any deadline inspection wins; lazy deadline check is a no-op afterwards.
        self::assertSame('shutdown', $canceller->getReason());
    }

    public function testAddListenerOnAlreadyExpiredDeadlineInvokesImmediately(): void
    {
        $canceller = new MethodCanceller($this->env, new \DateTimeImmutable('-1 second'));

        $fired = false;
        $canceller->addListener(ClosureMethodCancellationListener::fromCallable(
            static function () use (&$fired): void {
                $fired = true;
            },
        ));

        self::assertTrue($fired, 'listener must fire synchronously when deadline already passed');
        self::assertStringContainsString('deadline exceeded', (string) $canceller->getReason());
    }

    public function testNoDeadlineNeverAutoCancels(): void
    {
        $canceller = new MethodCanceller($this->env);

        self::assertFalse($canceller->isCancelled());
    }

    public function testListenersInvokedInRegistrationOrder(): void
    {
        $canceller = new MethodCanceller($this->env);
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
