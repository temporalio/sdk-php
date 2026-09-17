<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

use Temporal\Api\Enums\V1\NexusHandlerErrorRetryBehavior;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\RetryBehavior;

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

    public function getErrorType(): ErrorType
    {
        return ErrorType::tryFrom($this->type) ?? ErrorType::Unknown;
    }

    public function getRetryBehaviorEnum(): RetryBehavior
    {
        return match ($this->retryBehavior) {
            NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_RETRYABLE => RetryBehavior::Retryable,
            NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_NON_RETRYABLE => RetryBehavior::NonRetryable,
            default => RetryBehavior::Unspecified,
        };
    }
}
