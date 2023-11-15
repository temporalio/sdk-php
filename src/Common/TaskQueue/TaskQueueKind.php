<?php

declare(strict_types=1);

namespace Temporal\Common\TaskQueue;

/**
 * @see \Temporal\Api\Enums\V1\TaskQueueKind
 */
enum TaskQueueKind: int
{
    case TaskQueueKindUnspecified = 0;

    /**
     * Tasks from a normal workflow task queue always include complete workflow history
     * The task queue specified by the user is always a normal task queue. There can be as many
     * workers as desired for a single normal task queue. All those workers may pick up tasks from
     * that queue.
     */
    case TaskQueueKindNormal = 1;

    /**
     * A sticky queue only includes new history since the last workflow task, and they are
     * per-worker.
     * Sticky queues are created dynamically by each worker during their start up. They only exist
     * for the lifetime of the worker process. Tasks in a sticky task queue are only available to
     * the worker that created the sticky queue.
     * Sticky queues are only for workflow tasks. There are no sticky task queues for activities.
     */
    case TaskQueueKindSticky = 2;
}
