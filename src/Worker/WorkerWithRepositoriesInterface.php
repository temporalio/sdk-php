<?php

namespace Temporal\Worker;

use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Repository\RepositoryInterface;

/**
 * This interface will be merged into WorkerInterface in version 3.0.
 */
interface WorkerWithRepositoriesInterface extends WorkerInterface
{
    /**
     * @return RepositoryInterface<WorkflowPrototype>
     */
    public function getWorkflows(): RepositoryInterface;

    /**
     * @return RepositoryInterface<ActivityPrototype>
     */
    public function getActivities(): RepositoryInterface;
}
