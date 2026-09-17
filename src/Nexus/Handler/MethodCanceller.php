<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Worker\Environment\EnvironmentInterface;

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

    public function __construct(
        private readonly EnvironmentInterface $env,
        private readonly ?\DateTimeImmutable $deadline = null,
    ) {
        $this->listeners = new \SplObjectStorage();
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
        $this->listeners->offsetSet($listener);
    }

    public function removeListener(MethodCancellationListenerInterface $listener): void
    {
        $this->listeners->offsetUnset($listener);
    }

    private static function formatDeadlineReason(\DateTimeImmutable $deadline): string
    {
        return \sprintf('deadline exceeded (%s)', $deadline->format(\DATE_ATOM));
    }

    private function checkDeadline(): void
    {
        if ($this->reason !== null || $this->deadline === null) {
            return;
        }
        if ($this->deadline > $this->env->now()) {
            return;
        }
        $this->cancel(self::formatDeadlineReason($this->deadline));
    }
}
