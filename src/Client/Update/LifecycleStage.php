<?php

declare(strict_types=1);

namespace Temporal\Client\Update;

/**
 * Specified by clients invoking workflow execution updates and used to indicate to the
 * server how long the client wishes to wait for a return value from the RPC.
 * If any value other than {@see LifecycleStage::StageCompleted} is sent by the
 * client then the RPC will complete before the update is finished and will
 * return a handle to the running update so that it can later be polled for
 * completion.
 *
 * @see \Temporal\Api\Enums\V1\UpdateWorkflowExecutionLifecycleStage
 */
enum LifecycleStage: int
{
    /**
     * An unspecified vale for this enum.
     */
    case StageUnspecified = 0;

    /**
     * The gRPC call will not return until the update request has been admitted
     * by the server - it may be the case that due to a considerations like load
     * or resource limits that an update is made to wait before the server will
     * indicate that it has been received and will be processed. This value
     * does not wait for any sort of acknowledgement from a worker.
     *
     * Note: the option is currently unimplemented.
     */
    case StageAdmitted = 1;

    /**
     * The gRPC call will not return until the update has passed validation on
     * a worker.
     */
    case StageAccepted = 2;

    /**
     * The gRPC call will not return until the update has executed to completion
     * on a worker and has either been rejected or returned a value or an error.
     */
    case StageCompleted = 3;
}
