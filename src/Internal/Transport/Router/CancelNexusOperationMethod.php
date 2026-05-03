<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Nexus\NexusInvocationRegistry;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

/**
 * Cancels the in-flight handler method (vs CancelNexusOperation which uses the
 * operation token). Late cancel = no-op. Cancellation is cooperative — handlers
 * must poll {@see \Temporal\Nexus\Handler\OperationContext::isMethodCancelled()}.
 *
 * Options: {invocationId: uint64, reason: string}.
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
            $this->invocations->get($invocationId)?->cancel($reason);
        }

        $resolver->resolve(EncodedValues::fromValues([]));
    }
}
