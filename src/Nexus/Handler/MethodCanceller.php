<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Psr\Clock\ClockInterface;
use Temporal\Internal\Support\SystemClock;

/**
 * Idempotent cancellation of an in-flight handler *method* (not the Nexus
 * operation). Optional `$deadline` auto-trips on the next inspection.
 * Listeners notified once, by identity.
 */
final class MethodCanceller
{
    private ?string $reason = null;

    /** @var \SplObjectStorage<MethodCancellationListenerInterface, null> */
    private readonly \SplObjectStorage $listeners;

    private readonly ClockInterface $clock;

    public function __construct(
        private readonly ?\DateTimeImmutable $deadline = null,
        ?ClockInterface $clock = null,
    ) {
        $this->listeners = new \SplObjectStorage();
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * @internal Shared with {@see \Temporal\Nexus\Handler\OperationContext}.
     */
    public static function formatDeadlineReason(\DateTimeImmutable $deadline): string
    {
        return \sprintf('deadline exceeded (%s)', $deadline->format(\DATE_ATOM));
    }

    public function isCancelled(): bool
    {
        $this->checkDeadline();
        return $this->reason !== null;
    }

    public function getReason(): ?string
    {
        $this->checkDeadline();
        return $this->reason;
    }

    public function getDeadline(): ?\DateTimeImmutable
    {
        return $this->deadline;
    }

    /**
     * Idempotent. Listeners run in registration order. Not reentrant.
     */
    public function cancel(string $reason): void
    {
        if ($this->reason !== null) {
            return;
        }
        $this->reason = $reason;
        foreach ($this->listeners as $listener) {
            $listener->cancelled();
        }
    }

    /**
     * If already cancelled, the listener is invoked synchronously and not stored.
     */
    public function addListener(MethodCancellationListenerInterface $listener): void
    {
        $this->checkDeadline();
        if ($this->reason !== null) {
            $listener->cancelled();
            return;
        }
        $this->listeners->attach($listener);
    }

    public function removeListener(MethodCancellationListenerInterface $listener): void
    {
        $this->listeners->detach($listener);
    }

    private function checkDeadline(): void
    {
        if ($this->reason !== null || $this->deadline === null) {
            return;
        }
        if ($this->deadline > $this->clock->now()) {
            return;
        }
        $this->cancel(self::formatDeadlineReason($this->deadline));
    }
}
