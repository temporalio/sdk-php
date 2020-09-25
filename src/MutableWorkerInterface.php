<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Worker\MutableActivityProviderInterface;
use Temporal\Client\Worker\MutableWorkflowProviderInterface;

/**
 * @psalm-type ExceptionHandler = \Closure(\Throwable): void
 */
interface MutableWorkerInterface extends
    WorkerInterface,
    MutableWorkflowProviderInterface,
    MutableActivityProviderInterface
{
    /**
     * @psalm-param ExceptionHandler
     *
     * @param \Closure $then
     */
    public function onError(\Closure $then): void;
}
