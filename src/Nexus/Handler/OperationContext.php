<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Link;
use Temporal\Worker\Environment\EnvironmentInterface;

/**
 * Context for operation handling.
 */
final class OperationContext
{
    public readonly HeaderCollection $headers;

    public readonly LinkCollection $links;
    private readonly ?MethodCanceller $methodCanceller;
    private readonly EnvironmentInterface $env;

    /**
     * @param array<string, string>|HeaderCollection $headers Reused if a collection, wrapped if an array (keys lowercased).
     * @param list<Link>|LinkCollection $links Reused if a collection, wrapped if a list.
     */
    public function __construct(
        public readonly string $service,
        public readonly string $operation,
        EnvironmentInterface $env,
        array|HeaderCollection $headers = [],
        public readonly ?\DateTimeImmutable $deadline = null,
        array|LinkCollection $links = [],
        ?MethodCanceller $methodCanceller = null,
    ) {
        $this->headers = $headers instanceof HeaderCollection ? $headers : new HeaderCollection($headers);
        $this->links = $links instanceof LinkCollection ? $links : new LinkCollection($links);
        $this->env = $env;
        $this->methodCanceller = $methodCanceller
            ?? ($deadline !== null ? new MethodCanceller($this->env, $deadline) : null);
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
}
