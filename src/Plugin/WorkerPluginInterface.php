<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Plugin;

use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;

/**
 * Plugin interface for configuring workers and worker factory.
 *
 * Configuration methods are called in registration order.
 *
 * Task queue name is available via {@see WorkerInterface::getID()}.
 */
interface WorkerPluginInterface extends PluginInterface
{
    /**
     * Modify worker factory configuration before it is fully initialized.
     *
     * @param callable(WorkerFactoryPluginContext): void $next Calls the next plugin or the final hook.
     */
    public function configureWorkerFactory(WorkerFactoryPluginContext $context, callable $next): void;

    /**
     * Modify worker configuration before the worker is created.
     *
     * Task queue name is available via {@see WorkerPluginContext::getTaskQueue()}.
     *
     * @param callable(WorkerPluginContext): void $next Calls the next plugin or the final hook.
     */
    public function configureWorker(WorkerPluginContext $context, callable $next): void;

    /**
     * Called after a worker is created, allowing plugins to register workflows,
     * activities, and other components on the worker.
     *
     * This method is called in forward (registration) order immediately after
     * the worker is created. This is the appropriate place for registrations
     * because it is called before the worker starts polling.
     *
     * Task queue name is available via {@see WorkerInterface::getID()}.
     *
     * @param callable(WorkerInterface): void $next Calls the next plugin or the final hook.
     */
    public function initializeWorker(WorkerInterface $worker, callable $next): void;

    /**
     * Wraps the worker factory run lifecycle using chain-of-responsibility.
     *
     * Each plugin wraps the next one in the chain. The innermost call
     * executes the actual processing loop. Plugins are chained in
     * registration order: the first registered plugin is the outermost wrapper.
     *
     * Use this to manage resources, add observability, or handle errors:
     *
     * ```php
     * public function run(WorkerFactoryInterface $factory, callable $next): int
     * {
     *     $pool = new ConnectionPool();
     *     try {
     *         return $next($factory);
     *     } finally {
     *         $pool->close();
     *     }
     * }
     * ```
     *
     * @param callable(WorkerFactoryInterface): int $next Calls the next plugin or the actual run loop.
     */
    public function run(WorkerFactoryInterface $factory, callable $next): int;
}
