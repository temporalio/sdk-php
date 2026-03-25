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
 * No-op defaults for {@see WorkerPluginInterface}.
 *
 * @implements WorkerPluginInterface
 */
trait WorkerPluginTrait
{
    public function configureWorkerFactory(WorkerFactoryPluginContext $context, callable $next): void
    {
        $next($context);
    }

    public function configureWorker(WorkerPluginContext $context, callable $next): void
    {
        $next($context);
    }

    public function initializeWorker(WorkerInterface $worker, callable $next): void
    {
        $next($worker);
    }

    public function run(WorkerFactoryInterface $factory, callable $next): int
    {
        return $next($factory);
    }
}
