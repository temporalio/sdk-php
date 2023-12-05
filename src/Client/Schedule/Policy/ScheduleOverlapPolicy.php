<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Policy;

/**
 * Controls what happens when a workflow would be started by a schedule, and is already running.
 *
 * @see \Temporal\Api\Enums\V1\ScheduleOverlapPolicy
 */
enum ScheduleOverlapPolicy: int
{
    case Unspecified = 0;

    /**
     * Default.
     * Don't start anything. When the workflow completes, the next scheduled event after that
     * time will be considered.
     */
    case Skip = 1;

    /**
     * Means start the workflow again soon as the current one completes, but only buffer one start in this way.
     * If another start is supposed to happen when the workflow is running, and one is already buffered,
     * then only the first one will be started after the running workflow finishes.
     */
    case BufferOne = 2;

    /**
     * Buffer up any number of starts to all happen sequentially, immediately after the running
     * workflow completes.
     */
    case BufferAll = 3;

    /**
     * If there is another workflow running, cancel it, and start the new one after the old one
     * completes cancellation.
     */
    case CancelOther = 4;

    /**
     * If there is another workflow running, terminate it and start the new one immediately.
     */
    case TerminateOther = 5;

    /**
     * Start any number of concurrent workflows. Note that with this policy, last completion result
     * and last failure will not be available since workflows are not sequential.
     */
    case AllowAll = 6;
}
