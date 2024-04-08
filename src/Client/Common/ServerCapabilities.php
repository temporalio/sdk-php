<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Common;

final class ServerCapabilities
{
    /**
     * @param bool $signalAndQueryHeader
     *       True if signal and query headers are supported.
     * @param bool $internalErrorDifferentiation
     *       True if internal errors are differentiated from other types of errors for purposes of
     *       retrying non-internal errors.
     *       When unset/false, clients retry all failures. When true, clients should only retry
     *       non-internal errors.
     * @param bool $activityFailureIncludeHeartbeat
     *       True if RespondActivityTaskFailed API supports including heartbeat details
     * @param bool $supportsSchedules
     *       Supports scheduled workflow features.
     * @param bool $encodedFailureAttributes
     *       True if server uses protos that include temporal.api.failure.v1.Failure.encoded_attributes
     * @param bool $buildIdBasedVersioning
     *       True if server supports dispatching Workflow and Activity tasks based on a worker's build_id
     *       (see:
     *       https://github.com/temporalio/proposals/blob/a123af3b559f43db16ea6dd31870bfb754c4dc5e/versioning/worker-versions.md)
     * @param bool $upsertMemo
     *       True if server supports upserting workflow memo
     * @param bool $eagerWorkflowStart
     *       True if server supports eager workflow task dispatching for the StartWorkflowExecution API
     * @param bool $sdkMetadata
     *       True if the server knows about the sdk metadata field on WFT completions and will record
     *       it in history
     * @param bool $countGroupByExecutionStatus
     *       True if the server supports count group by execution status
     *       (-- api-linter: core::0140::prepositions=disabled --)
     */
    public function __construct(
        public readonly bool $signalAndQueryHeader = false,
        public readonly bool $internalErrorDifferentiation = false,
        public readonly bool $activityFailureIncludeHeartbeat = false,
        public readonly bool $supportsSchedules = false,
        public readonly bool $encodedFailureAttributes = false,
        public readonly bool $buildIdBasedVersioning = false,
        public readonly bool $upsertMemo = false,
        public readonly bool $eagerWorkflowStart = false,
        public readonly bool $sdkMetadata = false,
        public readonly bool $countGroupByExecutionStatus = false,
    ) {
    }

    /**
     * True if signal and query headers are supported.
     *
     * @deprecated Use {@see self::$signalAndQueryHeader} instead.
     */
    public function isSignalAndQueryHeaderSupports(): bool
    {
        return $this->signalAndQueryHeader;
    }

    /**
     * True if internal errors are differentiated from other types of errors for purposes of
     * retrying non-internal errors.
     * When unset/false, clients retry all failures. When true, clients should only retry
     * non-internal errors.
     *
     * @deprecated Use {@see self::$internalErrorDifferentiation} instead.
     */
    public function isInternalErrorDifferentiation(): bool
    {
        return $this->internalErrorDifferentiation;
    }
}

\class_alias(ServerCapabilities::class, 'Temporal\Client\ServerCapabilities');
