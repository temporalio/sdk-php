<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Api\Nexus\V1\CancelOperationRequest;
use Temporal\Api\Nexus\V1\Request;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

/**
 * Route for "CancelNexusOperation" from RR — wire-level Nexus cancel.
 *
 * Targets an async operation by its Nexus-spec coordinates
 * `(service, operation, operationToken)` and dispatches to the user-defined
 * `#[OperationCancel]` method. The cancel-routine itself is invoked
 * synchronously and is **not** interrupt-able from the worker side: an
 * `InterruptNexusInvocation` request will not flip
 * {@see \Temporal\Nexus\Handler\OperationContext::isMethodCancelled()}
 * for it, because no canceller is registered for cancel-routines. Authors
 * should keep cancel bodies fast and idempotent.
 *
 * Pair: {@see CancelNexusOperationMethod} interrupts an in-flight handler
 * invocation by RR-internal `invocationId` (cooperative interrupt of the
 * PHP call), not by Nexus-spec coordinates.
 *
 * Options: `{service, operation, operationToken}`.
 */
final class CancelNexusOperation extends Route
{
    public function __construct(
        private readonly NexusTaskHandler $taskHandler,
        private readonly MarshallerInterface $marshaller,
    ) {}

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        try {
            $options = $request->getOptions();
            $operationContext = $this->marshaller->unmarshal($options, new NexusOperationContext());
            $protoRequest = self::buildProtoRequest($options);
            $this->taskHandler->handleCancelOperation(
                $protoRequest,
                $operationContext,
            );
            $resolver->resolve(EncodedValues::fromValues([]));
        } catch (NexusHandlerErrorException $e) {
            // Unwrap the proto-shaped wrapper produced by NexusTaskHandler — the
            // outbound FailureConverter switches on the typed HandlerException
            // (NexusHandlerException) to emit NexusHandlerFailureInfo on the wire.
            $resolver->reject($e->getPrevious() ?? $e);
        } catch (\Throwable $e) {
            $resolver->reject($e);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function buildProtoRequest(array $options): Request
    {
        $cancelRequest = (new CancelOperationRequest())
            ->setService((string) ($options['service'] ?? ''))
            ->setOperation((string) ($options['operation'] ?? ''))
            ->setOperationToken((string) ($options['operationToken'] ?? ''));

        return (new Request())
            ->setHeader((array) ($options['headers'] ?? []))
            ->setCancelOperation($cancelRequest);
    }
}
