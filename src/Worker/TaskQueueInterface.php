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
use Temporal\Internal\Events\EventListenerInterface;
use Temporal\Internal\Repository\Identifiable;

interface TaskQueueInterface extends
    EventListenerInterface,
    DispatcherInterface,
    Identifiable
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @param class-string $class
     * @param bool $overwrite
     * @return $this
     */
    public function addWorkflow(string $class, bool $overwrite = false): self;

    /**
     * @return iterable<WorkflowPrototype>
     */
    public function getWorkflows(): iterable;

    /**
     * @param class-string $class
     * @param bool $overwrite
     * @return $this
     */
    public function addActivity(string $class, bool $overwrite = false): self;

    /**
     * @return iterable<ActivityPrototype>
     */
    public function getActivities(): iterable;
}
