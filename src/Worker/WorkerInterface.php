<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;

/**
 * Worker manages the execution of workflows and activities within the single TaskQueue. Activity and Workflow processing
 * will be launched using separate processes.
 */
interface WorkerInterface
{
    /**
     * Returns processing options associated with specific worker task queue.
     *
     * @return WorkerOptions
     */
    public function getOptions(): WorkerOptions;

    /**
     * Register one or multiple workflow types to be served by worker. Each workflow implementation is stateful so
     * method expects workflow class names instead of actual instances.
     *
     * @param class-string ...$class
     * @return $this
     */
    public function registerWorkflowTypes(string ...$class): self;

    /**
     * Returns list of registered workflow prototypes.
     *
     * @return iterable<WorkflowPrototype>
     */
    public function getWorkflows(): iterable;

    /**
     * Register one or multiple activity instances to be served by worker task queue. Activity implementation must
     * be stateless.
     *
     * @param object ...$activity
     * @return $this
     */
    public function registerActivityImplementations(object ...$activity): self;

    /**
     * Returns list of registered activities.
     *
     * @return iterable<ActivityPrototype>
     */
    public function getActivities(): iterable;
}
