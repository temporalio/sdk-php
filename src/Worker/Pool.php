<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;


class Pool implements PoolInterface
{
    /**
     * @var array|WorkerInterface[]
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
    public function add(WorkerInterface $worker): void
    {
        $this->workers[$worker->getTaskQueue()] = $worker;
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $taskQueue): ?WorkerInterface
    {
        return $this->workers[$taskQueue] ?? null;
    }
}
