<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

/**
 * The {@see WorkerFactoryInterface} is responsible for providing an
 * interface for registering all dependencies and creating a global
 * event loop ({@see LoopInterface}).
 *
 * In addition, implementation of this interface is responsible for delegating
 * events that came from the Temporal server to a specific TaskQueue.
 */
interface WorkerFactoryInterface
{
    /**
     * The name of the standard task queue into which all events from the
     * Temporal server will fall.
     *
     * @var string
     */
    public const DEFAULT_TASK_QUEUE = 'default';

    /**
     * Create a new Temporal Worker with the name of the task queue and register in worker.
     *
     * @param string $taskQueue
     * @param WorkerOptions|null $options
     * @return WorkerInterface
     */
    public function newWorker(
        string $taskQueue = self::DEFAULT_TASK_QUEUE,
        WorkerOptions $options = null,
    ): WorkerInterface;

    /**
     * Start processing workflows and activities processing.
     */
    public function run();
}
