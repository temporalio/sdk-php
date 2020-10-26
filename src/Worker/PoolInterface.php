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
 * @implements \IteratorAggregate<WorkerInterface>
 */
interface PoolInterface extends \IteratorAggregate, \Countable
{
    /**
     * @param WorkerInterface $worker
     */
    public function add(WorkerInterface $worker): void;

    /**
     * @param string $taskQueue
     * @return WorkerInterface|null
     */
    public function find(string $taskQueue): ?WorkerInterface;
}
