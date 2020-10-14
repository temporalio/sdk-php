<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Workflow;

use Temporal\Client\Worker\WorkflowWorkerInterface;

/**
 * A collection implementation that contains all initialized workers with their
 * states at the time of initialization: for example, a list of workflows.
 *
 * @internal Pool is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client\Worker
 */
final class Pool
{
    /**
     * @var array|Process[]
     */
    private array $workers = [];

    /**
     * @param WorkflowWorkerInterface $worker
     * @return Process
     * @throws \Exception
     */
    public function create(WorkflowWorkerInterface $worker): Process
    {
        $process = new Process(clone $worker);

        return $this->workers[$process->getId()] = $process;
    }
}
