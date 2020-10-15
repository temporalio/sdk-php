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
 * @psalm-type ExceptionHandler = callable(\Throwable): void
 */
interface WorkerInterface
{
    /**
     * @var string
     */
    public const DEFAULT_TASK_QUEUE = 'default';

    /**
     * @param string $name
     * @return int
     */
    public function run(string $name = self::DEFAULT_TASK_QUEUE): int;

    /**
     * @psalm-param ExceptionHandler
     *
     * @param callable $then
     * @return static
     */
    public function onError(callable $then): self;

    /**
     * @param callable $then
     * @return static
     */
    public function onTick(callable $then): self;
}
