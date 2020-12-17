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

class ArrayQueue implements QueueInterface
{
    /**
     * @psalm-var list<array-key, CommandInterface>
     */
    protected array $commands = [];

    /**
     * Queue constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param int $commandId
     * @return CommandInterface|null
     */
    public function pull(int $commandId): ?CommandInterface
    {
        foreach ($this->commands as $i => $command) {
            if ($command->getId() === $commandId) {
                unset($this->commands[$i]);

                break;
            }
        }

        return $command ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        while (\count($this->commands)) {
            yield \array_shift($this->commands);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->commands);
    }

    /**
     * {@inheritDoc}
     */
    public function push(CommandInterface $command): void
    {
        $this->commands[] = $command;
    }
}
