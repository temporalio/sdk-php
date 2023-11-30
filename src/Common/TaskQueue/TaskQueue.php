<?php

declare(strict_types=1);

namespace Temporal\Common\TaskQueue;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Traits\CloneWith;

/**
 * @link https://docs.temporal.io/docs/concepts/task-queues/
 * @see \Temporal\Api\Taskqueue\V1\TaskQueue
 */
final class TaskQueue implements \Stringable
{
    use CloneWith;

    #[Marshal]
    public readonly string $name;

    public function __toString(): string
    {
        return $this->name;
    }

    private function __construct(string $name)
    {
        $this->name = $name;
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
}
