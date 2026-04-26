<?php

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Nexus\Sdk\Handler\MethodCanceller;

/**
 * Maps RR `InvocationID` → MethodCanceller for in-flight handlers.
 * Lookup miss = late cancel after the handler finished, treat as no-op.
 */
final class NexusInvocationRegistry
{
    /** @var array<int, MethodCanceller> */
    private array $cancellers = [];

    public function register(int $invocationId, MethodCanceller $canceller): void
    {
        $this->cancellers[$invocationId] = $canceller;
    }

    public function unregister(int $invocationId): void
    {
        unset($this->cancellers[$invocationId]);
    }

    public function get(int $invocationId): ?MethodCanceller
    {
        return $this->cancellers[$invocationId] ?? null;
    }
}
