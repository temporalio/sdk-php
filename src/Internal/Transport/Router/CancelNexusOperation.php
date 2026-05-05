<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use React\Promise\Deferred;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Nexus\Validation\OperationNameValidator;
use Temporal\Nexus\Validation\OperationTokenValidator;
use Temporal\Nexus\Validation\ServiceNameValidator;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

/**
 * Route for "CancelNexusOperation" from RR — wire-level Nexus cancel.
 *
 * Targets an async operation by its Nexus-spec coordinates
 * `(service, operation, operationToken)` and dispatches to the user-defined
 * `#[OperationCancel]` method. The cancel-routine itself is invoked
 * synchronously and is **not** interrupt-able from the worker side: an
 * `InterruptNexusInvocation` request will not flip
 * {@see OperationContext::isMethodCancelled()} for it, because no canceller
 * is registered for cancel-routines. Authors should keep cancel bodies fast
 * and idempotent.
 *
 * Pair: {@see InterruptNexusInvocation} interrupts an in-flight handler
 * invocation by RR-internal `invocationId` (cooperative interrupt of the
 * PHP call), not by Nexus-spec coordinates.
 *
 * Options: {service, operation, operationToken}.
 */
final class CancelNexusOperation extends Route
{
    public function __construct(
        private readonly NexusTaskHandler $taskHandler,
    ) {}

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();

        $service = $options['service'] ?? '';
        $operation = $options['operation'] ?? '';
        $operationToken = $options['operationToken'] ?? '';

        try {
            ServiceNameValidator::assert($service);
            OperationNameValidator::assert($operation);
            OperationTokenValidator::assert($operationToken);

            $context = new OperationContext(
                service: $service,
                operation: $operation,
            );

            $details = new OperationCancelDetails(operationToken: $operationToken);

            $this->taskHandler->cancelOperationDirect($context, $details);
            $resolver->resolve(EncodedValues::fromValues([]));
        } catch (\Throwable $e) {
            $resolver->reject($e);
        }
    }
}
