<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Nexus\NexusInvocationRegistry;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

/**
 * Handles "CancelNexusOperationMethod" command from Go RoadRunner plugin.
 *
 * Unlike `CancelNexusOperation` (which cancels the business Nexus operation
 * via its token), this cancels the **handler method** itself — e.g. on pool
 * shutdown or deadline expiry. It is a one-way signal: we resolve with an
 * empty payload regardless of whether a matching in-flight invocation was
 * found (a late cancel that races with handler completion is a legitimate
 * no-op).
 *
 * Options: `{invocationId: uint64, reason: string}`.
 *
 * PHP is single-threaded, so cancellation is cooperative — long-running
 * handlers must poll `$context->isMethodCancelled()` or register a listener
 * via `$context->addMethodCancellationListener()`.
 */
final class CancelNexusOperationMethod extends Route
{
    public function __construct(
        private readonly NexusInvocationRegistry $invocations,
    ) {}

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();
        $invocationId = (int) ($options['invocationId'] ?? 0);
        $reason = (string) ($options['reason'] ?? '');

        if ($invocationId !== 0) {
            // Null when the handler has already finished — treated as no-op.
            $this->invocations->get($invocationId)?->cancel($reason);
        }

        $resolver->resolve(EncodedValues::fromValues([]));
    }
}
