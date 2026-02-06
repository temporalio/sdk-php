<?php

declare(strict_types=1);

namespace Temporal\Activity;

use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Common\Priority;

/**
 * Optional priority settings that control relative ordering of task processing
 * when tasks are backed up in a queue.
 *
 * Defaults to inheriting priority from the workflow that scheduled the activity.
 *
 * @internal Experimental
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class ActivityPriority
{
    public Priority $priority;

    public function __construct(Priority|array $priority)
    {
        if (\is_array($priority)) {
            $priority = new Priority(
                priorityKey: $priority['priorityKey'] ?? $priority['priority_key'] ?? 0,
                fairnessKey: $priority['fairnessKey'] ?? $priority['fairness_key'] ?? '',
                fairnessWeight: $priority['fairnessWeight'] ?? $priority['fairness_weight'] ?? 0.0,
            );
        }
        $this->priority = $priority;
    }

    /**
     * Named constructor for fluent interface.
     */
    public static function new(int $value = 0): self
    {
        return new self(Priority::new($value));
    }
}
