<?php

declare(strict_types=1);

namespace Temporal\Common\TaskQueue;

use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * @link https://docs.temporal.io/docs/concepts/task-queues/
 * @see \Temporal\Api\Taskqueue\V1\TaskQueue
 */
final class TaskQueue
{
    #[Marshal]
    public readonly string $name;

    /**
     * Default: {@see TaskQueueKind::TaskQueueKindNormal}
     */
    #[Marshal]
    public readonly TaskQueueKind $kind;

    /**
     * Iff kind == {@see TaskQueueKind::TaskQueueKindSticky}, then this field contains the name of
     * the normal task queue that the sticky worker is running on.
     */
    #[Marshal(name: 'normal_name')]
    public readonly string $normalName;
}
