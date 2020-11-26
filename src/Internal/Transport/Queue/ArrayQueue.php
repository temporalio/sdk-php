<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Queue;

use Temporal\Client\Internal\Transport\Protocol\Command\CommandInterface;

class ArrayQueue implements QueueInterface
{
    /**
     * @psalm-var list<array-key, CommandInterface>
     */
    private array $queue = [];

    /**
     * Queue constructor.
     */
    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        while (\count($this->queue)) {
            yield \array_shift($this->queue);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->queue);
    }

    /**
     * {@inheritDoc}
     */
    public function push(CommandInterface $command): void
    {
        $this->queue[] = $command;
    }
}
