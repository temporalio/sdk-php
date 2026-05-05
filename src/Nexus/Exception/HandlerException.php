<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Exception;

/**
 * Thrown from a handler.
 */
final class HandlerException extends NexusException
{
    public readonly string $rawErrorType;
    public readonly ErrorType $errorType;
    public readonly RetryBehavior $retryBehavior;

    private function __construct(
        ErrorType|string $errorType,
        string $message,
        ?\Throwable $cause,
        RetryBehavior $retryBehavior,
    ) {
        if ($errorType instanceof ErrorType) {
            $this->rawErrorType = $errorType->value;
            $this->errorType = $errorType;
        } else {
            $this->rawErrorType = $errorType;
            $this->errorType = ErrorType::tryFrom($errorType) ?? ErrorType::Unknown;
        }
        $this->retryBehavior = $retryBehavior;

        parent::__construct($message, 0, $cause);
    }

    public static function create(
        ErrorType $errorType,
        string $message,
        ?\Throwable $cause = null,
        RetryBehavior $retryBehavior = RetryBehavior::Unspecified,
    ): self {
        return new self($errorType, $message, $cause, $retryBehavior);
    }

    /**
     * Message is derived from the cause's own message.
     */
    public static function fromCause(
        ErrorType $errorType,
        \Throwable $cause,
        RetryBehavior $retryBehavior = RetryBehavior::Unspecified,
    ): self {
        $message = $cause->getMessage() !== ''
            ? "handler error: {$cause->getMessage()}"
            : 'handler error';
        return new self($errorType, $message, $cause, $retryBehavior);
    }

    /**
     * Unknown wire values → {@see ErrorType::Unknown}; raw value preserved in {@see self::$rawErrorType}.
     */
    public static function fromRawType(
        string $rawErrorType,
        string $message,
        ?\Throwable $cause = null,
        RetryBehavior $retryBehavior = RetryBehavior::Unspecified,
    ): self {
        return new self($rawErrorType, $message, $cause, $retryBehavior);
    }

    public function isRetryable(): bool
    {
        if ($this->retryBehavior !== RetryBehavior::Unspecified) {
            return $this->retryBehavior === RetryBehavior::Retryable;
        }

        return match ($this->errorType) {
            ErrorType::BadRequest,
            ErrorType::Unauthenticated,
            ErrorType::Unauthorized,
            ErrorType::NotFound,
            ErrorType::Conflict,
            ErrorType::NotImplemented => false,
            default => true,
        };
    }
}
