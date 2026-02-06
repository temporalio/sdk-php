<?php

declare(strict_types=1);

namespace Temporal\Activity;

use Spiral\Attributes\NamedArgumentConstructor;

/**
 * Task queue that the activity needs to be scheduled on.
 *
 * Optional: The default task queue with the same name as the workflow task queue.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class TaskQueue
{
    public string $name;

    public function __construct(string|array $name)
    {
        if (\is_array($name)) {
            $name = $name['value'] ?? $name['name'] ?? (\array_values($name)[0] ?? '');
        }
        $this->name = (string) $name;
    }
}
