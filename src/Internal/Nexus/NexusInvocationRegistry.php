<?php

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Nexus\Sdk\Handler\MethodCanceller;

/**
 * Tracks `MethodCanceller` instances for in-flight Nexus invocations, keyed
 * by the wire `InvocationID` that RoadRunner assigns to the
 * `InvokeNexusOperation` message.
 *
 * This lets the `CancelNexusOperationMethod` route look up the canceller for
 * the target invocation and trigger `cancel()` on it, which in turn flips
 * `OperationContext::isMethodCancelled()` and invokes any registered
 * listeners.
 *
 * Semantics:
 *  - `register()` stamps the canceller under the given id; any existing
 *    entry is silently overwritten (the Go side guarantees monotonic ids,
 *    collisions can only happen on broken clients).
 *  - `unregister()` is idempotent — safe to call multiple times.
 *  - `get()` returns `null` for unknown/already-finished ids. Callers must
 *    treat this as a no-op (late cancel after handler finished).
 *
 * PHP is single-threaded, so the map is not synchronised.
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
