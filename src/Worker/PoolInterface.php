<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

/**
 * The task of the {@see PoolInterface} is to be able to register a
 * new {@see WorkerInterface} and return it by the task queue identifier.
 *
 * @implements \IteratorAggregate<WorkerInterface>
 */
interface PoolInterface extends \IteratorAggregate, \Countable
{
    /**
     * Register a new {@see WorkerInterface} inside the worker pool.
     *
     * @param WorkerInterface $worker
     */
    public function add(WorkerInterface $worker): void;

    /**
     * Returns a {@see WorkerInterface} by its task queue identifier
     * or {@see null} in the case that such worker with passed task queue
     * identifier argument was not found.
     *
     * @param string $taskQueue
     * @return WorkerInterface|null
     */
    public function find(string $taskQueue): ?WorkerInterface;
}
