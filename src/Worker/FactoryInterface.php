<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Temporal\Client\Transport\ClientProviderInterface;

/**
 * The {@see FactoryInterface} is responsible for providing an interface for
 * registering all dependencies and creating a global event loop.
 */
interface FactoryInterface extends ClientProviderInterface, LoopInterface
{
    /**
     * @var string
     */
    public const DEFAULT_TASK_QUEUE = 'default';

    /**
     * @param string $taskQueue
     * @return WorkerInterface
     */
    public function create(string $taskQueue = self::DEFAULT_TASK_QUEUE): WorkerInterface;
}
