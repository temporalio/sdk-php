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

interface WorkerInterface
{
    /**
     * @param class-string ...$class
     * @return $this
     */
    public function registerWorkflowTypes(string ...$class): self;

    /**
     * @return iterable<WorkflowPrototype>
     */
    public function getWorkflows(): iterable;

    /**
     * @param object ...$activity
     * @return $this
     */
    public function registerActivityImplementations(object ...$activity): self;

    /**
     * @return iterable<ActivityPrototype>
     */
    public function getActivities(): iterable;
}
