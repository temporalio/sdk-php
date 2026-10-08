<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Exception\OutOfContextException;
use Temporal\Workflow;

/**
 * @template T
 */
final class WorkflowLocal
{
    /** @var \WeakMap<object, array{T}> */
    private \WeakMap $values;

    /** @var \Closure(): T */
    private readonly \Closure $initial;

    /**
     * @param null|callable(): T $initial
     */
    public function __construct(?callable $initial = null)
    {
        $this->values = new \WeakMap();
        $this->initial = $initial === null ? static fn(): mixed => null : $initial(...);
    }

    /**
     * @return T
     * @throws OutOfContextException
     */
    public function get(): mixed
    {
        $instance = Workflow::getInstance();
        $entry = $this->values[$instance] ?? null;
        if ($entry === null) {
            $entry = [($this->initial)()];
            $this->values[$instance] = $entry;
        }

        return $entry[0];
    }

    /**
     * @param T $value
     * @throws OutOfContextException
     */
    public function set(mixed $value): void
    {
        $this->values[Workflow::getInstance()] = [$value];
    }
}
