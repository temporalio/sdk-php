<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Queue;

use Temporal\Client\Worker\Command\CommandInterface;

class SplQueue implements QueueInterface
{
    /**
     * @psalm-var \SplDoublyLinkedList<array-key, CommandInterface>
     * @var \SplDoublyLinkedList
     */
    private \SplDoublyLinkedList $queue;

    /**
     * Queue constructor.
     */
    public function __construct()
    {
        $this->queue = $this->create();
    }

    /**
     * @return \SplDoublyLinkedList
     */
    protected function create(): \SplDoublyLinkedList
    {
        $queue = new \SplQueue();
        $queue->setIteratorMode(\SplQueue::IT_MODE_DELETE);

        return $queue;
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        return $this->queue;
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return $this->queue->count();
    }

    /**
     * {@inheritDoc}
     */
    public function push(CommandInterface $command): void
    {
        $this->queue->push($command);
    }
}
