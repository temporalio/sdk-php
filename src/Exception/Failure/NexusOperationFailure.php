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
 * Workflow-side failure for a Nexus operation invoked via
 * {@see \Temporal\Workflow::executeNexusOperation()}.
 * `$previous` carries the cause as a {@see TemporalFailure}.
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
            ]),
            $message,
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
