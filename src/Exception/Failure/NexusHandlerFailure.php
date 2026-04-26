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
 * Transport-level Nexus HandlerError (`BAD_REQUEST`, `INTERNAL`, `NOT_FOUND`, etc.).
 * Maps 1:1 to the `NexusHandlerException` thrown on the handler side.
 */
class NexusHandlerFailure extends TemporalFailure
{
    public function __construct(
        string $message,
        private readonly string $type,
        private readonly int $retryBehavior,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, null, $previous);
    }

    /**
     * Raw error-type string (e.g. `BAD_REQUEST`, or user-defined).
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@see \Temporal\Api\Enums\V1\NexusHandlerErrorRetryBehavior} value.
     */
    public function getRetryBehavior(): int
    {
        return $this->retryBehavior;
    }
}
