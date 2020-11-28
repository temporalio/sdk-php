<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Pool;

use Temporal\Client\Worker\TaskQueueInterface;

/**
 * The task of the {@see PoolInterface} is to be able to register a
 * new {@see TaskQueueInterface} and return it by the task queue identifier.
 *
 * @implements \IteratorAggregate<TaskQueueInterface>
 */
interface PoolInterface extends \IteratorAggregate, \Countable
{
    /**
     * Register a new {@see TaskQueueInterface} inside the worker pool.
     *
     * @param TaskQueueInterface $queue
     */
    public function add(TaskQueueInterface $queue): void;

    /**
     * Returns a {@see TaskQueueInterface} by its task queue identifier
     * or {@see null} in the case that such worker with passed task queue
     * identifier argument was not found.
     *
     * @param string $taskQueue
     * @return TaskQueueInterface|null
     */
    public function find(string $taskQueue): ?TaskQueueInterface;
}
