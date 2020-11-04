<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Evenement\EventEmitterInterface;
use Temporal\Client\Transport\ClientProviderInterface;

/**
 * @implements EventEmitterInterface<FactoryInterface::ON_*>
 */
interface FactoryInterface extends EventEmitterInterface, ClientProviderInterface
{
    /**
     * @var string
     */
    public const ON_TICK = 'tick';

    /**
     * @var string
     */
    public const DEFAULT_TASK_QUEUE = 'default';

    /**
     * @param string $taskQueue
     * @return WorkerInterface
     */
    public function create(string $taskQueue = self::DEFAULT_TASK_QUEUE): WorkerInterface;

    /**
     * @return int
     */
    public function start(): int;
}
