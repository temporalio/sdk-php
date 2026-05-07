<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Google\Rpc\Code;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\RetryBehavior;

/**
 * Maps SDK-level exceptions raised inside a Nexus handler to typed
 * {@see HandlerException}. Mirrors the Java SDK's `convertKnownFailures` +
 * `convertStatusRuntimeExceptionToHandlerException` table so wire behaviour
 * is identical across SDKs.
 *
 * @internal
 */
final class HandlerErrorMapper
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Returns a typed {@see HandlerException} for known shapes:
     *  - {@see ApplicationFailure} with `nonRetryable=true` → INTERNAL + NonRetryable;
     *  - {@see ServiceClientException} → table over {@see Code}.
     * Returns null when nothing matches; the caller decides whether to
     * wrap in a generic INTERNAL HandlerException or rethrow.
     */
    public static function mapToHandlerException(\Throwable $e): ?HandlerException
    {
        if ($e instanceof ApplicationFailure && $e->isNonRetryable()) {
            return HandlerException::fromCause(ErrorType::Internal, $e, RetryBehavior::NonRetryable);
        }

        if ($e instanceof ServiceClientException) {
            return self::fromGrpcCode($e);
        }

        return null;
    }

    private static function fromGrpcCode(ServiceClientException $e): HandlerException
    {
        return match ($e->getCode()) {
            Code::INVALID_ARGUMENT => HandlerException::fromCause(ErrorType::BadRequest, $e),
            Code::ALREADY_EXISTS,
            Code::FAILED_PRECONDITION,
            Code::OUT_OF_RANGE => HandlerException::fromCause(ErrorType::Internal, $e, RetryBehavior::NonRetryable),
            Code::ABORTED,
            Code::UNAVAILABLE => HandlerException::fromCause(ErrorType::Unavailable, $e),
            // Unauthenticated/PermissionDenied collapse to Internal: these surface
            // when the handler itself fails to auth with Temporal, not when the
            // caller's auth is bad — should be treated as retryable.
            Code::CANCELLED,
            Code::DATA_LOSS,
            Code::INTERNAL,
            Code::UNKNOWN,
            Code::UNAUTHENTICATED,
            Code::PERMISSION_DENIED => HandlerException::fromCause(ErrorType::Internal, $e),
            Code::NOT_FOUND => HandlerException::fromCause(ErrorType::NotFound, $e),
            Code::RESOURCE_EXHAUSTED => HandlerException::fromCause(ErrorType::ResourceExhausted, $e),
            Code::UNIMPLEMENTED => HandlerException::fromCause(ErrorType::NotImplemented, $e),
            Code::DEADLINE_EXCEEDED => HandlerException::fromCause(ErrorType::UpstreamTimeout, $e),
            default => HandlerException::fromCause(ErrorType::Internal, $e),
        };
    }
}
