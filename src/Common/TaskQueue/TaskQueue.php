<?php

declare(strict_types=1);

namespace Temporal\Common\TaskQueue;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Traits\CloneWith;

/**
 * @link https://docs.temporal.io/docs/concepts/task-queues/
 * @see \Temporal\Api\Taskqueue\V1\TaskQueue
 */
final class TaskQueue
{
    use CloneWith;

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

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->kind = TaskQueueKind::TaskQueueKindUnspecified;
        $this->normalName = '';
    }

    public static function new(string $name): self
    {
        return new self($name);
    }

    public function withName(string $name): self
    {
        /** @see self::$name */
        return $this->with('name', $name);
    }

    public function withKind(TaskQueueKind $kind): self
    {
        /** @see self::$kind */
        return $this->with('kind', $kind);
    }
}
