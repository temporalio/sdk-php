<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Events\EventEmitterInterface;
use Temporal\Client\Internal\Events\EventListenerInterface;

/**
 * @implements EventEmitterInterface<TaskQueueInterface::ON_*>
 */
interface TaskQueueInterface extends
    EventListenerInterface,
    DispatcherInterface,
    EventEmitterInterface
{
    /**
     * @var string
     */
    public const ON_SIGNAL = 'signal';

    /**
     * @var string
     */
    public const ON_QUERY = 'query';

    /**
     * @var string
     */
    public const ON_CALLBACK = 'callback';

    /**
     * @var string
     */
    public const ON_TICK = 'tick';

    /**
     * @return string
     */
    public function getName(): string;

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
