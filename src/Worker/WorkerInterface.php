<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

use Closure;
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
     * Register activity finalizer which is a callback being called after each activity. This
     * can be used to clean up resources in your application.
     */
    public function registerActivityFinalizer(Closure $finalizer): self;

    /**
     * Returns list of registered workflow prototypes.
     *
     * @return iterable<WorkflowPrototype>
     */
    public function getWorkflows(): iterable;

    /**
     * @deprecated use registerActivity() instead
     * @see \Temporal\Worker\WorkerInterface::registerActivity()
     * Register one or multiple activity instances to be served by worker task queue. Activity implementation must
     * be stateless.
     *
     * @param object ...$activity
     * @return $this
     */
    public function registerActivityImplementations(object ...$activity): self;

    /**
     * Register an activity via its type or via a factory. When an activity class doesn't require
     * any external dependencies and can be created with a keyword `new`:
     *
     * $worker->registerActivity(MyActivity::class);
     *
     * In case an activity class requires some external dependencies provide a callback - factory
     * that creates or builds a new activity instance. The factory should be a callable which accepts
     * an instance of ReflectionClass with an activity class which should be created.
     *
     * $worker->registerActivity(MyActivity::class, fn(ReflectionClass $class) => $container->create($class->getName()));
     */
    public function registerActivity(string $type, callable $factory = null): self;

    /**
     * Returns list of registered activities.
     *
     * @return iterable<ActivityPrototype>
     */
    public function getActivities(): iterable;
}
