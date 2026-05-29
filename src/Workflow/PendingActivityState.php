<?php

declare(strict_types=1);

namespace Temporal\Workflow;

/**
 * Pending activity state.
 *
 * @see \Temporal\Api\Enums\V1\PendingActivityState
 */
enum PendingActivityState: int
{
    case Unspecified = 0;
    case Scheduled = 1;
    case Started = 2;
    case CancelRequested = 3;

    /**
     * Activity is paused on the server, and is not running in the worker.
     */
    case Paused = 4;

    /**
     * Activity is currently running on the worker, but paused on the server.
     */
    case PauseRequested = 5;
}
