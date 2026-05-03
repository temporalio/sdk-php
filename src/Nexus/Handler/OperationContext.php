<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Internal\Headers;
use Temporal\Nexus\Link;
use Temporal\Nexus\ServiceDefinition;
use Psr\Clock\ClockInterface;

/**
 * Context for operation handling. Immutable except for {@see $links}, which
 * is shared so middleware-produced links stay visible across
 * `withServiceDefinition()`. If `$deadline` is given without an explicit
 * `$methodCanceller`, one is auto-created from it — so deadline expiry trips
 * registered cancellation listeners.
 */
final class OperationContext
{
    /** @var array<string, string> Lowercased keys. */
    public readonly array $headers;

    public readonly LinkCollection $links;
    private readonly ?MethodCanceller $methodCanceller;
    private readonly ClockInterface $clock;

    /**
     * @param array<string, string> $headers Lowercased on construction.
     * @param list<Link>|LinkCollection $links Reused if a collection, wrapped if a list.
     */
    public function __construct(
        public readonly string $service,
        public readonly string $operation,
        array $headers = [],
        public readonly ?\DateTimeImmutable $deadline = null,
        public readonly ?ServiceDefinition $serviceDefinition = null,
        array|LinkCollection $links = [],
        ?MethodCanceller $methodCanceller = null,
        ?ClockInterface $clock = null,
    ) {
        $this->headers = Headers::normalize($headers);
        $this->links = $links instanceof LinkCollection ? $links : new LinkCollection($links);
        $this->clock = $clock ?? new SystemClock();
        $this->methodCanceller = $methodCanceller
            ?? ($deadline !== null ? new MethodCanceller($deadline, $this->clock) : null);
    }

    /**
     * True if the canceller fired or the deadline has passed. Not the same as
     * Nexus operation cancellation.
     */
    public function isMethodCancelled(): bool
    {
        return $this->methodCanceller?->isCancelled() === true;
    }

    /**
     * Reason from {@see MethodCanceller::cancel()} or `"deadline exceeded (...)"`.
     */
    public function getMethodCancellationReason(): ?string
    {
        return $this->methodCanceller?->getReason();
    }

    public function isDeadlineExceeded(): bool
    {
        return $this->deadline !== null && $this->deadline <= $this->clock->now();
    }

    /**
     * No-op when no canceller (and no deadline) attached. If already
     * cancelled, the listener runs synchronously here.
     */
    public function addMethodCancellationListener(MethodCancellationListenerInterface $listener): self
    {
        $this->methodCanceller?->addListener($listener);
        return $this;
    }

    public function removeMethodCancellationListener(MethodCancellationListenerInterface $listener): self
    {
        $this->methodCanceller?->removeListener($listener);
        return $this;
    }

    /**
     * New context with a different service definition. Shares links, canceller, clock.
     */
    public function withServiceDefinition(ServiceDefinition $serviceDefinition): self
    {
        return new self(
            $this->service,
            $this->operation,
            $this->headers,
            $this->deadline,
            $serviceDefinition,
            $this->links,
            $this->methodCanceller,
            $this->clock,
        );
    }
}
