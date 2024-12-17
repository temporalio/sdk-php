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

/**
 * @implements \IteratorAggregate<array-key, CommandInterface>
 */
interface QueueInterface extends \IteratorAggregate, \Countable
{
    public function push(CommandInterface $command): void;

    public function pull(int $commandId): ?CommandInterface;

    public function has(int $commandId): bool;
}
