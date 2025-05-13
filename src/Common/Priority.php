<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Common;

use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * Priority contains metadata that controls the relative ordering of task processing when tasks are
 * backed up in a queue. The affected queues depend on the server version.
 *
 * Priority is attached to workflows and activities. By default, activities and child workflows
 * inherit Priority from the workflow that created them, but may override fields when an activity is
 * started or modified.
 *
 * For all fields, the field not present or equal to zero/empty string means to inherit the value
 * from the calling workflow, or if there is no calling workflow, then use the default value.
 *
 * @internal The feature is experimental and may change in the future.
 */
final class Priority
{
    /**
     * A priority key is a positive integer from 1 to n, where smaller integers correspond to higher
     * priorities (tasks run sooner). In general, tasks in a queue should be processed in close to
     * priority order, although small deviations are possible.
     *
     * The maximum priority value (minimum priority) is determined by server configuration, and
     * defaults to 5.
     *
     * The default value when 0 is calculated by (min+max)/2. With the default max of 5,
     * and min of 1, that comes out to 3.
     *
     * @var int<0, max>
     */
    #[Marshal(name: 'PriorityKey')]
    public int $priorityKey = 0;

    private function __construct(int $priorityKey = 0)
    {
        $this->priorityKey = $priorityKey;
    }

    public static function new(int $priorityKey = 0): self
    {
        return new self($priorityKey);
    }

    /**
     * A priority key is a positive integer from 1 to n, where smaller integers correspond to higher
     * priorities (tasks run sooner). In general, tasks in a queue should be processed in close to
     * priority order, although small deviations are possible.
     *
     * @param int<0, max> $value
     * @return $this
     */
    public function withPriorityKey(int $value): self
    {
        $clone = clone $this;
        $clone->priorityKey = $value;
        return $clone;
    }

    /**
     * @internal for internal use only
     */
    public function toProto(): \Temporal\Api\Common\V1\Priority
    {
        return (new \Temporal\Api\Common\V1\Priority())
            ->setPriorityKey($this->priorityKey);
    }
}
