<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Queue;

use Temporal\Worker\Transport\Command\CommandInterface;

class ArrayQueue implements QueueInterface
{
    /**
     * @psalm-var list<array-key, CommandInterface>
     */
    protected array $commands = [];

    /**
     * Queue constructor.
     */
    public function __construct() {}

    public function pull(int $commandId): ?CommandInterface
    {
        foreach ($this->commands as $i => $command) {
            if ($command->getID() === $commandId) {
                unset($this->commands[$i]);
                return $command;
            }
        }

        return null;
    }

    public function has(int $commandId): bool
    {
        foreach ($this->commands as $command) {
            if ($command->getID() === $commandId) {
                return true;
            }
        }

        return false;
    }

    public function getIterator(): \Traversable
    {
        while (\count($this->commands)) {
            yield \array_shift($this->commands);
        }
    }

    public function count(): int
    {
        return \count($this->commands);
    }

    public function push(CommandInterface $command): void
    {
        $this->commands[] = $command;
    }
}
