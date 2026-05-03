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
use Temporal\Nexus\Exception\HandlerErrorFailure;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationErrorFailure;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\FailureInfo;

/**
 * Single producer for the Nexus proto envelope, mirroring the Workflow/Activity
 * {@see FailureConverter} pattern. Routes through the canonical JSON-envelope
 * builders ({@see OperationErrorFailure::from()} / {@see HandlerErrorFailure::from()})
 * so the on-wire metadata/details shape is decided in one place.
 *
 * The Nexus proto `Failure` is structurally thinner than the Temporal-failure
 * proto used by `FailureConverter`: it has only `message`, `metadata`,
 * `details`, no recursive `cause` field. Cause-chain trace data, if requested,
 * travels inline in `details` under {@see self::DETAILS_TRACEBACK_KEY}.
 *
 * @internal
 */
final class NexusFailureConverter
{
    /** Reserved key inside `Failure.details` JSON for the flat cause chain. */
    public const DETAILS_TRACEBACK_KEY = '_traceback';

    /** Bounded — guard against cyclic cause chain. */
    public const MAX_CAUSE_DEPTH = 16;

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Pack an {@see OperationException} into the proto-envelope
     * {@see UnsuccessfulOperationError}, going through the canonical
     * {@see OperationErrorFailure::from()} for content (metadata.type,
     * details.state). The proto `operationState` field is set redundantly
     * because the proto schema requires it.
     */
    public static function operationExceptionToProto(
        OperationException $e,
        bool $includeTraceback = true,
    ): UnsuccessfulOperationError {
        $info = OperationErrorFailure::from($e, self::tracebackExtras($e, $includeTraceback));

        $opError = new UnsuccessfulOperationError();
        $opError->setOperationState($e->state->value);
        $opError->setFailure(self::failureInfoToProto($info));
        return $opError;
    }

    /**
     * Pack a {@see HandlerException} into the proto-envelope {@see HandlerError}.
     * Same routing pattern as {@see self::operationExceptionToProto()}.
     */
    public static function handlerExceptionToProto(
        HandlerException $e,
        bool $includeTraceback = true,
    ): HandlerError {
        $info = HandlerErrorFailure::from($e, self::tracebackExtras($e, $includeTraceback));

        $handlerError = new HandlerError();
        $handlerError->setErrorType($e->rawErrorType);
        $handlerError->setRetryBehavior(FailureConverter::mapNexusRetryBehavior($e->retryBehavior));
        $handlerError->setFailure(self::failureInfoToProto($info));
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
     * Map a canonical {@see FailureInfo} (JSON-envelope shape) onto the
     * {@see NexusProtoFailure}. The recursive `FailureInfo::$cause` field is
     * not transcoded — proto callers must pre-pack cause data into
     * `detailsJson` via {@see self::flattenCauseChain()} if they want it on
     * the wire.
     */
    public static function failureInfoToProto(FailureInfo $info): NexusProtoFailure
    {
        $proto = new NexusProtoFailure();
        $proto->setMessage($info->message);
        $info->metadata === [] or $proto->setMetadata($info->metadata);
        $info->detailsJson === null or $proto->setDetails($info->detailsJson);
        return $proto;
    }

    /**
     * @return array<string, mixed>
     */
    private static function tracebackExtras(\Throwable $e, bool $includeTraceback): array
    {
        if (!$includeTraceback) {
            return [];
        }
        return [self::DETAILS_TRACEBACK_KEY => self::flattenCauseChain($e)];
    }
}
