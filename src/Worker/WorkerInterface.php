<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Temporal\Client\Protocol\DispatcherInterface;
use Temporal\Client\Worker\Declaration\Repository\ActivityRepositoryInterface;
use Temporal\Client\Worker\Declaration\Repository\WorkflowRepositoryInterface;

interface WorkerInterface extends
    WorkflowRepositoryInterface,
    ActivityRepositoryInterface,
    DispatcherInterface
{
    /**
     * @return string
     */
    public function getTaskQueue(): string;
}
