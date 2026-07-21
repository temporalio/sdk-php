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
use Temporal\Exception\Client\WorkflowException;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\RetryBehavior;

/**
 * @internal
 */
final class HandlerErrorMapper
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    public static function mapToHandlerException(\Throwable $e): ?HandlerException
    {
        if ($e instanceof ApplicationFailure && $e->isNonRetryable()) {
            return HandlerException::fromCause(ErrorType::Internal, $e, RetryBehavior::NonRetryable);
        }

        if ($e instanceof WorkflowNotFoundException) {
            return HandlerException::fromCause(ErrorType::NotFound, $e);
        }

        if ($e instanceof WorkflowExecutionAlreadyStartedException) {
            return HandlerException::fromCause(ErrorType::Internal, $e, RetryBehavior::NonRetryable);
        }

        if ($e instanceof WorkflowException) {
            $previous = $e->getPrevious();
            if ($previous instanceof ServiceClientException) {
                return self::fromGrpcCode($previous);
            }
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
            // Unauthenticated/PermissionDenied collapse to Internal: a handler-side auth failure against Temporal, not a Nexus-caller auth error.
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
