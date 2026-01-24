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
 * @see \Temporal\Api\Common\V1\Priority
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
    #[Marshal(name: 'priority_key')]
    #[Marshal(name: 'PriorityKey')]
    public int $priorityKey = 0;

    /**
     * FairnessKey is a short string that's used as a key for a fairness
     * balancing mechanism. It may correspond to a tenant id, or to a fixed
     * string like "high" or "low". The default is the empty string.
     *
     * The fairness mechanism attempts to dispatch tasks for a given key in
     * proportion to its weight. For example, using a thousand distinct tenant
     * ids, each with a weight of 1.0 (the default) will result in each tenant
     * getting a roughly equal share of task dispatch throughput.
     *
     * Fairness keys are limited to 64 bytes.
     */
    #[Marshal(name: 'fairness_key')]
    #[Marshal(name: 'FairnessKey')]
    public string $fairnessKey = '';

    /**
     * FairnessWeight for a task can come from multiple sources for
     * flexibility. From highest to lowest precedence:
     * 1. Weights for a small set of keys can be overridden in task queue
     *    configuration with an API.
     * 2. It can be attached to the workflow/activity in this field.
     * 3. The default weight of 1.0 will be used.
     *
     * Weight values are clamped to the range [0.001, 1000].
     */
    #[Marshal(name: 'fairness_weight')]
    #[Marshal(name: 'FairnessWeight')]
    public float $fairnessWeight = 0.0;

    /**
     * @param int<0, max> $priorityKey
     */
    private function __construct(int $priorityKey = 0)
    {
        $this->priorityKey = $priorityKey;
    }

    /**
     * Create a new Priority instance.
     */
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
     * FairnessKey is a short string that's used as a key for a fairness
     * balancing mechanism. It may correspond to a tenant id, or to a fixed
     * string like "high" or "low". The default is the empty string.
     *
     * @return $this
     */
    public function withFairnessKey(string $value): self
    {
        $clone = clone $this;
        $clone->fairnessKey = $value;
        return $clone;
    }

    /**
     * FairnessWeight for a task can come from multiple sources for
     * flexibility. From highest to lowest precedence:
     * 1. Weights for a small set of keys can be overridden in task queue
     *    configuration with an API.
     * 2. It can be attached to the workflow/activity in this field.
     * 3. The default weight of 1.0 will be used.
     *
     *  Weight values are clamped to the range [0.001, 1000].
     *
     * @return $this
     */
    public function withFairnessWeight(float $value): self
    {
        $value < 0.001 or $value > 1000.0 and throw new \InvalidArgumentException(
            'FairnessWeight must be in the range [0.001, 1000].',
        );
        $clone = clone $this;
        $clone->fairnessWeight = $value;
        return $clone;
    }

    /**
     * @internal for internal use only
     */
    public function toProto(): \Temporal\Api\Common\V1\Priority
    {
        return (new \Temporal\Api\Common\V1\Priority())
            ->setPriorityKey($this->priorityKey)
            ->setFairnessKey($this->fairnessKey)
            ->setFairnessWeight($this->fairnessWeight);
    }
}
