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
 * The {@see FactoryInterface} is responsible for providing an
 * interface for registering all dependencies and creating a global
 * event loop ({@see LoopInterface}).
 *
 * In addition, implementation of this interface is responsible for delegating
 * events that came from the Temporal server to a specific TaskQueue.
 */
interface FactoryInterface extends LoopInterface
{
    /**
     * The name of the standard task queue into which all events from the
     * Temporal server will fall.
     *
     * @var string
     */
    public const DEFAULT_TASK_QUEUE = 'default';

    /**
     * Create a new Temporal QueueWorker with the name of the task queue.
     *
     * Note: When starting the global event loop ({@see LoopInterface::run()}),
     * all workers created with this method ({@see FactoryInterface::createWorker()})
     * will be launched.
     *
     * @param string $taskQueue
     * @return TaskQueueInterface
     */
    public function create(string $taskQueue = self::DEFAULT_TASK_QUEUE): TaskQueueInterface;

    /**
     * Create a new Temporal QueueWorker with the name of the task queue and
     * register in worker.
     *
     * @param string $taskQueue
     * @return TaskQueueInterface
     */
    public function createAndRegister(string $taskQueue = self::DEFAULT_TASK_QUEUE): TaskQueueInterface;

    /**
     * Register Temporal QueueWorker in worker.
     *
     * @param TaskQueueInterface $queue
     */
    public function register(TaskQueueInterface $queue): void;
}
