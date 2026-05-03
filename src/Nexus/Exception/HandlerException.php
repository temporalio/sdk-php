<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Exception;

use Temporal\Nexus\FailureInfo;

/**
 * Thrown from a handler. Use {@see self::create()}, {@see self::fromCause()},
 * or {@see self::fromRawType()} as factories.
 */
final class HandlerException extends NexusException
{
    public readonly string $rawErrorType;
    public readonly ErrorType $errorType;
    public readonly RetryBehavior $retryBehavior;
    public readonly ?FailureInfo $originalFailure;

    private function __construct(
        ErrorType|string $errorType,
        string $message,
        ?\Throwable $cause,
        RetryBehavior $retryBehavior,
        ?FailureInfo $originalFailure,
    ) {
        if ($errorType instanceof ErrorType) {
            $this->rawErrorType = $errorType->value;
            $this->errorType = $errorType;
        } else {
            $this->rawErrorType = $errorType;
            $this->errorType = ErrorType::tryFrom($errorType) ?? ErrorType::Unknown;
        }
        $this->retryBehavior = $retryBehavior;
        $this->originalFailure = $originalFailure;

        parent::__construct($message, 0, $cause);
    }

    public static function create(
        ErrorType $errorType,
        string $message,
        ?\Throwable $cause = null,
        RetryBehavior $retryBehavior = RetryBehavior::Unspecified,
        ?FailureInfo $originalFailure = null,
    ): self {
        return new self($errorType, $message, $cause, $retryBehavior, $originalFailure);
    }

    /**
     * Message is derived from the cause's own message.
     */
    public static function fromCause(
        ErrorType $errorType,
        \Throwable $cause,
        RetryBehavior $retryBehavior = RetryBehavior::Unspecified,
        ?FailureInfo $originalFailure = null,
    ): self {
        $message = $cause->getMessage() !== ''
            ? "handler error: {$cause->getMessage()}"
            : 'handler error';
        return new self($errorType, $message, $cause, $retryBehavior, $originalFailure);
    }

    /**
     * Unknown wire values → {@see ErrorType::Unknown}; raw value preserved in {@see self::$rawErrorType}.
     */
    public static function fromRawType(
        string $rawErrorType,
        string $message,
        ?\Throwable $cause = null,
        RetryBehavior $retryBehavior = RetryBehavior::Unspecified,
        ?FailureInfo $originalFailure = null,
    ): self {
        return new self($rawErrorType, $message, $cause, $retryBehavior, $originalFailure);
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
