<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

/**
 * Typed exception for a Nexus operation failure carried over the wire as
 * {@see \Temporal\Api\Failure\V1\NexusOperationFailureInfo}.
 *
 * Raised on the workflow side when a Nexus operation — invoked via
 * {@see \Temporal\Workflow::executeNexusOperation()} — fails or is cancelled.
 * The `$previous` throwable carries the cause wrapped as a
 * {@see TemporalFailure} (usually an {@see ApplicationFailure} when the
 * handler returned an `OperationError`).
 */
class NexusOperationFailure extends TemporalFailure
{
    public function __construct(
        string $message,
        private readonly int $scheduledEventId,
        private readonly string $endpoint,
        private readonly string $service,
        private readonly string $operation,
        private readonly string $operationToken,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            self::buildMessage([
                'endpoint' => $endpoint,
                'service' => $service,
                'operation' => $operation,
                'operationToken' => $operationToken,
                'scheduledEventId' => $scheduledEventId,
                'originalMessage' => $message,
            ]),
            null,
            $previous,
        );
    }

    public function getScheduledEventId(): int
    {
        return $this->scheduledEventId;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Async operation token issued by the handler — empty for sync operations.
     */
    public function getOperationToken(): string
    {
        return $this->operationToken;
    }
}
