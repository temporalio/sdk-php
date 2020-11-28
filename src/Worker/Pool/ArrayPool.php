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

class ArrayPool implements PoolInterface
{
    /**
     * @var array|TaskQueueInterface[]
     */
    private array $workers = [];

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->workers);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->workers);
    }

    /**
     * {@inheritDoc}
     */
    public function add(TaskQueueInterface $queue): void
    {
        $this->workers[$queue->getName()] = $queue;
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $taskQueue): ?TaskQueueInterface
    {
        return $this->workers[$taskQueue] ?? null;
    }
}
