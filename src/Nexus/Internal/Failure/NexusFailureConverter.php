<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Internal\Failure;

use Temporal\Api\Nexus\V1\Failure as NexusProtoFailure;
use Temporal\Api\Nexus\V1\HandlerError;
use Temporal\Api\Nexus\V1\UnsuccessfulOperationError;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Exception\RetryBehavior;

/**
 * Single producer for the Nexus proto envelope, mirroring the Workflow/Activity
 * {@see FailureConverter} pattern: PHP exception → proto without an intermediate
 * PHP-side DTO.
 *
 * The Nexus proto `Failure` is structurally thinner than the Temporal-failure
 * proto used by `FailureConverter`: it has only `message`, `metadata`,
 * `details`, no recursive `cause` field. Cause-chain trace data, if requested,
 * travels inline in `details` under {@see self::DETAILS_TRACEBACK_KEY}.
 *
 * Wire-shape constants (`OPERATION_ERROR_TYPE`, `HANDLER_ERROR_TYPE`,
 * `METADATA_TYPE_KEY`, `DETAILS_*`) are part of the Nexus protocol contract;
 * keep them in lockstep with the spec.
 *
 * @see https://github.com/nexus-rpc/api/blob/main/SPEC.md
 *
 * @internal
 */
final class NexusFailureConverter
{
    /** Value of `metadata.type` that marks an OperationError failure. */
    public const OPERATION_ERROR_TYPE = 'nexus.OperationError';

    /** Value of `metadata.type` that marks a HandlerError failure. */
    public const HANDLER_ERROR_TYPE = 'nexus.HandlerError';

    /** Key in the `metadata` map that carries the failure-shape discriminator. */
    public const METADATA_TYPE_KEY = 'type';

    /** Key inside `details` that carries an OperationError's terminal state. */
    public const DETAILS_STATE_KEY = 'state';

    /** Key inside `details` that carries a HandlerError's predefined error type. */
    public const DETAILS_TYPE_KEY = 'type';

    /** Key inside `details` that overrides a HandlerError's default retry semantics. */
    public const DETAILS_RETRYABLE_OVERRIDE_KEY = 'retryableOverride';

    /** Reserved key inside `details` for the flat cause chain. */
    public const DETAILS_TRACEBACK_KEY = '_traceback';

    /** Bounded — guard against cyclic cause chain. */
    public const MAX_CAUSE_DEPTH = 16;

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Pack an {@see OperationException} into the proto-envelope
     * {@see UnsuccessfulOperationError}. The proto `operationState` field
     * mirrors `details.state` because the proto schema requires it.
     */
    public static function operationExceptionToProto(
        OperationException $e,
        bool $includeTraceback = true,
    ): UnsuccessfulOperationError {
        $details = self::tracebackDetails($e, $includeTraceback);
        $details[self::DETAILS_STATE_KEY] = $e->state->value;

        $opError = new UnsuccessfulOperationError();
        $opError->setOperationState($e->state->value);
        $opError->setFailure(self::buildProtoFailure(
            $e->getMessage(),
            self::OPERATION_ERROR_TYPE,
            $details,
        ));
        return $opError;
    }

    /**
     * Pack a {@see HandlerException} into the proto-envelope {@see HandlerError}.
     */
    public static function handlerExceptionToProto(
        HandlerException $e,
        bool $includeTraceback = true,
    ): HandlerError {
        $details = self::tracebackDetails($e, $includeTraceback);
        $details[self::DETAILS_TYPE_KEY] = $e->rawErrorType;
        if ($e->retryBehavior === RetryBehavior::Retryable) {
            $details[self::DETAILS_RETRYABLE_OVERRIDE_KEY] = true;
        } elseif ($e->retryBehavior === RetryBehavior::NonRetryable) {
            $details[self::DETAILS_RETRYABLE_OVERRIDE_KEY] = false;
        }

        $handlerError = new HandlerError();
        $handlerError->setErrorType($e->rawErrorType);
        $handlerError->setRetryBehavior(FailureConverter::mapNexusRetryBehavior($e->retryBehavior));
        $handlerError->setFailure(self::buildProtoFailure(
            $e->getMessage(),
            self::HANDLER_ERROR_TYPE,
            $details,
        ));
        return $handlerError;
    }

    /**
     * Walk {@see \Throwable::getPrevious()} chain (≤ {@see self::MAX_CAUSE_DEPTH})
     * and serialize each level as `{type, message, trace}`. The Nexus proto
     * envelope has no recursive `cause` field, so this flat representation is
     * the only way to preserve trace data on the proto wire.
     *
     * @return list<array{type: class-string, message: string, trace: string}>
     */
    public static function flattenCauseChain(\Throwable $e, int $maxDepth = self::MAX_CAUSE_DEPTH): array
    {
        $chain = [];
        $cursor = $e;
        for ($depth = 0; $cursor !== null && $depth < $maxDepth; $depth++) {
            $chain[] = [
                'type' => $cursor::class,
                'message' => $cursor->getMessage(),
                'trace' => $cursor->getTraceAsString(),
            ];
            $cursor = $cursor->getPrevious();
        }
        return $chain;
    }

    /**
     * @return array<string, mixed>
     */
    private static function tracebackDetails(\Throwable $e, bool $includeTraceback): array
    {
        if (!$includeTraceback) {
            return [];
        }
        return [self::DETAILS_TRACEBACK_KEY => self::flattenCauseChain($e)];
    }

    /**
     * @param array<string, mixed> $details
     */
    private static function buildProtoFailure(
        string $message,
        string $metadataType,
        array $details,
    ): NexusProtoFailure {
        $proto = new NexusProtoFailure();
        $proto->setMessage($message);
        $proto->setMetadata([self::METADATA_TYPE_KEY => $metadataType]);
        $proto->setDetails(\json_encode($details, \JSON_THROW_ON_ERROR));
        return $proto;
    }
}
