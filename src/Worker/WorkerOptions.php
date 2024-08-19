<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

use JetBrains\PhpStorm\Pure;
use Temporal\Activity\ActivityOptions;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Support\DateInterval;

/**
 * @psalm-import-type DateIntervalValue from DateInterval
 */
class WorkerOptions
{
    /**
     * Optional: To set the maximum concurrent activity executions this worker can have.
     *
     * The zero value of this uses the default value.
     */
    #[Marshal(name: 'MaxConcurrentActivityExecutionSize')]
    public int $maxConcurrentActivityExecutionSize = 0;

    /**
     * Optional: Sets the rate limiting on number of activities that can be
     * executed per second per worker. This can be used to limit resources used
     * by the worker.
     *
     * Notice that the number is represented in float, so that you can set it
     * to less than 1 if needed. For example, set the number to 0.1 means you
     * want your activity to be executed once for every 10 seconds. This can be
     * used to protect down stream services from flooding.
     * The zero value of this uses the default value.
     */
    #[Marshal(name: 'WorkerActivitiesPerSecond')]
    public float $workerActivitiesPerSecond = 0;

    /**
     * Optional: To set the maximum concurrent local activity executions this
     * worker can have.
     *
     * The zero value of this uses the default value.
     */
    #[Marshal(name: 'MaxConcurrentLocalActivityExecutionSize')]
    public int $maxConcurrentLocalActivityExecutionSize = 0;

    /**
     * Optional: Sets the rate limiting on number of local activities that can
     * be executed per second per worker. This can be used to limit resources
     * used by the worker.
     *
     * Notice that the number is represented in float, so that you can set it
     * to less than 1 if needed. For example, set the number to 0.1 means you
     * want your local activity to be executed once for every 10 seconds. This
     * can be used to protect down stream services from flooding.
     *
     * The zero value of this uses the default value.
     */
    #[Marshal(name: 'WorkerLocalActivitiesPerSecond')]
    public float $workerLocalActivitiesPerSecond = 0;

    /**
     * Optional: Sets the rate limiting on number of activities that can be
     * executed per second.
     *
     * This is managed by the server and controls activities per second for your
     * entire taskqueue whereas WorkerActivityTasksPerSecond controls activities
     * only per worker.
     *
     * Notice that the number is represented in float, so that you can set it
     * to less than 1 if needed. For example, set the number to 0.1 means you
     * want your activity to be executed once for every 10 seconds. This can be
     * used to protect down stream services from flooding.
     *
     * The zero value of this uses the default value.
     *
     * @note Setting this to a non zero value will also disable eager activities.
     */
    #[Marshal(name: 'TaskQueueActivitiesPerSecond')]
    public float $taskQueueActivitiesPerSecond = 0;

    /**
     * Optional: Sets the maximum number of goroutines that will concurrently
     * poll the temporal-server to retrieve activity tasks. Changing this value
     * will affect the rate at which the worker is able to consume tasks from
     * a task queue.
     */
    #[Marshal(name: 'MaxConcurrentActivityTaskPollers')]
    public int $maxConcurrentActivityTaskPollers = 0;

    /**
     * Optional: To set the maximum concurrent workflow task executions this
     * worker can have.
     *
     * The zero value of this uses the default value.
     * Due to internal logic where pollers alternate between stick and non-sticky queues, this
     * value cannot be 1 and will panic if set to that value.
     */
    #[Marshal(name: 'MaxConcurrentWorkflowTaskExecutionSize')]
    public int $maxConcurrentWorkflowTaskExecutionSize = 0;

    /**
     * Optional: Sets the maximum number of goroutines that will concurrently
     * poll the temporal-server to retrieve workflow tasks. Changing this value
     * will affect the rate at which the worker is able to consume tasks from
     * a task queue. Due to
     * internal logic where pollers alternate between stick and non-sticky queues, this
     * value cannot be 1 and will panic if set to that value.
     */
    #[Marshal(name: 'MaxConcurrentWorkflowTaskPollers')]
    public int $maxConcurrentWorkflowTaskPollers = 0;

    /**
     * Optional: Sets the maximum concurrent nexus task executions this worker can have.
     * The zero value of this uses the default value.
     */
    #[Marshal(name: 'MaxConcurrentNexusTaskExecutionSize')]
    public int $maxConcurrentNexusTaskExecutionSize = 0;

    /**
     * Optional: Sets the maximum number of goroutines that will concurrently poll the
     * temporal-server to retrieve nexus tasks. Changing this value will affect the
     * rate at which the worker is able to consume tasks from a task queue.
     */
    #[Marshal(name: 'MaxConcurrentNexusTaskPollers')]
    public int $maxConcurrentNexusTaskPollers = 0;

    /**
     * Optional: Enable logging in replay.
     *
     * In the workflow code you can use workflow.GetLogger(ctx) to write logs. By default, the logger will skip log
     * entry during replay mode so you won't see duplicate logs. This option will enable the logging in replay mode.
     * This is only useful for debugging purpose.
     */
    #[Marshal(name: 'EnableLoggingInReplay')]
    public bool $enableLoggingInReplay = false;

    /**
     * Optional: Sticky schedule to start timeout.
     *
     * The resolution is seconds.
     *
     * Sticky Execution is to run the workflow tasks for one workflow execution on same worker host. This is an
     * optimization for workflow execution. When sticky execution is enabled, worker keeps the workflow state in
     * memory. New workflow task contains the new history events will be dispatched to the same worker. If this
     * worker crashes, the sticky workflow task will timeout after StickyScheduleToStartTimeout, and temporal server
     * will clear the stickiness for that workflow execution and automatically reschedule a new workflow task that
     * is available for any worker to pick up and resume the progress.
     *
     * Default: 5s
     */
    #[Marshal(name: 'StickyScheduleToStartTimeout', type: NullableType::class, of: DateIntervalType::class)]
    public ?\DateInterval $stickyScheduleToStartTimeout = null;

    /**
     * Optional: Sets how workflow worker deals with non-deterministic history events
     * (presumably arising from non-deterministic workflow definitions or non-backward compatible workflow
     * definition changes) and other panics raised from workflow code.
     */
    #[Marshal(name: 'WorkflowPanicPolicy', type: WorkflowPanicPolicy::class)]
    public WorkflowPanicPolicy $workflowPanicPolicy = WorkflowPanicPolicy::BlockWorkflow;

    /**
     * Optional: worker graceful stop timeout.
     */
    #[Marshal(name: 'WorkerStopTimeout', type: NullableType::class, of: DateIntervalType::class)]
    public ?\DateInterval $workerStopTimeout = null;

    /**
     * Optional: Enable running session workers.
     *
     * Session workers is for activities within a session.
     * Enable this option to allow worker to process sessions.
     */
    #[Marshal(name: 'EnableSessionWorker')]
    public bool $enableSessionWorker = false;

    /**
     * Optional: The identifier of the resource consumed by sessions.
     *
     * It's the user's responsibility to ensure there's only one worker using
     * this resourceID.
     *
     * For now, if user doesn't specify one, a new uuid will be used as the
     * resourceID.
     */
    #[Marshal(name: 'SessionResourceID')]
    public ?string $sessionResourceId = null;

    /**
     * Optional: Sets the maximum number of concurrently running sessions the resource supports.
     */
    #[Marshal(name: 'MaxConcurrentSessionExecutionSize')]
    public int $maxConcurrentSessionExecutionSize = 1000;

    /**
     * Optional: If set to true, a workflow worker is not started for this
     * worker and workflows cannot be registered with this worker. Use this if
     * you only want your worker to execute activities.
     */
    #[Marshal(name: 'DisableWorkflowWorker')]
    public bool $disableWorkflowWorker = false;

    /**
     * Optional: If set to true worker would only handle workflow tasks and local activities.
     * Non-local activities will not be executed by this worker.
     */
    #[Marshal(name: 'LocalActivityWorkerOnly')]
    public bool $localActivityWorkerOnly = false;

    /**
     * Optional: If set overwrites the client level Identify value.
     * default: client identity
     */
    #[Marshal(name: 'Identity')]
    public string $identity = '';

    /**
     * Optional: If set defines maximum amount of time that workflow task will be allowed to run.
     * Default: 1 sec.
     */
    #[Marshal(name: 'DeadlockDetectionTimeout', type: NullableType::class, of: DateIntervalType::class)]
    public ?\DateInterval $deadlockDetectionTimeout = null;

    /**
     * Optional: The default amount of time between sending each pending heartbeat to the server.
     * This is used if the ActivityOptions do not provide a HeartbeatTimeout.
     * Otherwise, the interval becomes a value a bit smaller than the given HeartbeatTimeout.
     *
     * Default: 30 seconds
     */
    #[Marshal(name: 'MaxHeartbeatThrottleInterval', type: NullableType::class, of: DateIntervalType::class)]
    public ?\DateInterval $maxHeartbeatThrottleInterval = null;

    /**
     * Optional: Disable eager activities. If set to true, activities will not
     * be requested to execute eagerly from the same workflow regardless
     * of {@see self::$maxConcurrentEagerActivityExecutionSize}.
     *
     * Eager activity execution means the server returns requested eager
     * activities directly from the workflow task back to this worker which is
     * faster than non-eager which may be dispatched to a separate worker.
     *
     * @note Eager activities will automatically be disabled if {@see self::$taskQueueActivitiesPerSecond} is set.
     */
    #[Marshal(name: 'DisableEagerActivities')]
    public bool $disableEagerActivities = false;

    /**
     * Optional: Maximum number of eager activities that can be running.
     *
     * When non-zero, eager activity execution will not be requested for
     * activities schedule by the workflow if it would cause the total number of
     * running eager activities to exceed this value. For example, if this is
     * set to 1000 and there are already 998 eager activities executing and a
     * workflow task schedules 3 more, only the first 2 will request eager
     * execution.
     *
     * The default of 0 means unlimited and therefore only bound by {@see self::$maxConcurrentActivityExecutionSize}.
     *
     * @see self::$disableEagerActivities for a description of eager activity execution.
     */
    #[Marshal(name: 'MaxConcurrentEagerActivityExecutionSize')]
    public int $maxConcurrentEagerActivityExecutionSize = 0;

    /**
     * Optional: Disable allowing workflow and activity functions that are
     * registered with custom names from being able to be called with their
     * function references.
     *
     * Users are strongly recommended to set this as true if they register any
     * workflow or activity functions with custom names. By leaving this as
     * false, the historical default, ambiguity can occur between function names
     * and aliased names when not using string names when executing child
     * workflow or activities.
     */
    #[Marshal(name: 'DisableRegistrationAliasing')]
    public bool $disableRegistrationAliasing = false;

    /**
     * Assign a BuildID to this worker. This replaces the deprecated binary checksum concept,
     * and is used to provide a unique identifier for a set of worker code, and is necessary
     * to opt in to the Worker Versioning feature. See {@see self::$useBuildIDForVersioning}.
     *
     * @internal Experimental
     */
    #[Marshal(name: 'BuildID')]
    public string $buildID = '';

    /**
     * Optional: If set, opts this worker into the Worker Versioning feature.
     * It will only operate on workflows it claims to be compatible with.
     * You must set {@see self::$buildID} if this flag is true.
     *
     * @internal Experimental
     * @note Cannot be enabled at the same time as {@see self::$enableSessionWorker}
     */
    #[Marshal(name: 'UseBuildIDForVersioning bool')]
    public bool $useBuildIDForVersioning = false;

    #[Pure]
    public static function new(): self
    {
        return new self();
    }

    /**
     * Optional: To set the maximum concurrent activity executions this worker can have.
     *
     * The zero value of this uses the default value.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param int<0, max> $size
     */
    #[Pure]
    public function withMaxConcurrentActivityExecutionSize(int $size): self
    {
        \assert($size >= 0);

        $self = clone $this;
        $self->maxConcurrentActivityExecutionSize = $size;
        return $self;
    }

    /**
     * Optional: Sets the rate limiting on number of activities that can be
     * executed per second per worker. This can be used to limit resources used
     * by the worker.
     *
     * Notice that the number is represented in float, so that you can set it
     * to less than 1 if needed. For example, set the number to 0.1 means you
     * want your activity to be executed once for every 10 seconds. This can be
     * used to protect down stream services from flooding.
     * The zero value of this uses the default value.
     *
     * @psalm-suppress ImpureMethodCall
     */
    #[Pure]
    public function withWorkerActivitiesPerSecond(float $interval): self
    {
        \assert($interval >= 0);

        $self = clone $this;
        $self->workerActivitiesPerSecond = $interval;
        return $self;
    }

    /**
     * Optional: To set the maximum concurrent local activity executions this
     * worker can have.
     *
     * The zero value of this uses the default value.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param int<0, max> $size
     */
    #[Pure]
    public function withMaxConcurrentLocalActivityExecutionSize(int $size): self
    {
        \assert($size >= 0);

        $self = clone $this;
        $self->maxConcurrentLocalActivityExecutionSize = $size;
        return $self;
    }

    /**
     * Optional: Sets the rate limiting on number of local activities that can
     * be executed per second per worker. This can be used to limit resources
     * used by the worker.
     *
     * Notice that the number is represented in float, so that you can set it
     * to less than 1 if needed. For example, set the number to 0.1 means you
     * want your local activity to be executed once for every 10 seconds. This
     * can be used to protect down stream services from flooding.
     *
     * The zero value of this uses the default value.
     *
     * @psalm-suppress ImpureMethodCall
     */
    #[Pure]
    public function withWorkerLocalActivitiesPerSecond(float $interval): self
    {
        \assert($interval >= 0);

        $self = clone $this;
        $self->workerLocalActivitiesPerSecond = $interval;
        return $self;
    }

    /**
     * Optional: Sets the rate limiting on number of activities that can be
     * executed per second.
     *
     * This is managed by the server and controls activities per second for your
     * entire taskqueue whereas WorkerActivityTasksPerSecond controls activities
     * only per worker.
     *
     * Notice that the number is represented in float, so that you can set it
     * to less than 1 if needed. For example, set the number to 0.1 means you
     * want your activity to be executed once for every 10 seconds. This can be
     * used to protect down stream services from flooding.
     *
     * The zero value of this uses the default value.
     *
     * @note Setting this to a non zero value will also disable eager activities.
     *
     * @psalm-suppress ImpureMethodCall
     */
    #[Pure]
    public function withTaskQueueActivitiesPerSecond(float $interval): self
    {
        \assert($interval >= 0);

        $self = clone $this;
        $self->taskQueueActivitiesPerSecond = $interval;
        return $self;
    }

    /**
     * Optional: Sets the maximum number of goroutines that will concurrently
     * poll the temporal-server to retrieve activity tasks. Changing this value
     * will affect the rate at which the worker is able to consume tasks from
     * a task queue.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param int<0, max> $pollers
     */
    #[Pure]
    public function withMaxConcurrentActivityTaskPollers(int $pollers): self
    {
        \assert($pollers >= 0);

        $self = clone $this;
        $self->maxConcurrentActivityTaskPollers = $pollers;
        return $self;
    }

    /**
     * Optional: To set the maximum concurrent workflow task executions this
     * worker can have.
     *
     * The zero value of this uses the default value.
     * Due to internal logic where pollers alternate between stick and non-sticky queues, this
     * value cannot be 1 and will panic if set to that value.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param int<0, max> $size
     */
    #[Pure]
    public function withMaxConcurrentWorkflowTaskExecutionSize(int $size): self
    {
        \assert($size >= 0);

        $self = clone $this;
        $self->maxConcurrentWorkflowTaskExecutionSize = $size;
        return $self;
    }

    /**
     * Optional: Sets the maximum number of goroutines that will concurrently
     * poll the temporal-server to retrieve workflow tasks. Changing this value
     * will affect the rate at which the worker is able to consume tasks from
     * a task queue. Due to
     * internal logic where pollers alternate between stick and non-sticky queues, this
     * value cannot be 1 and will panic if set to that value.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param int<0, max> $pollers
     */
    #[Pure]
    public function withMaxConcurrentWorkflowTaskPollers(int $pollers): self
    {
        \assert($pollers >= 0);

        $self = clone $this;
        $self->maxConcurrentWorkflowTaskPollers = $pollers;
        return $self;
    }

    /**
     * Optional: Sets the maximum concurrent nexus task executions this worker can have.
     * The zero value of this uses the default value.
     *
     * @param int<0, max> $size
     */
    #[Pure]
    public function withMaxConcurrentNexusTaskExecutionSize(int $size): self
    {
        \assert($size >= 0);

        $self = clone $this;
        $self->maxConcurrentNexusTaskExecutionSize = $size;
        return $self;
    }

    /**
     * Optional: Sets the maximum number of goroutines that will concurrently
     * poll the temporal-server to retrieve nexus tasks. Changing this value will affect the
     * rate at which the worker is able to consume tasks from a task queue.
     *
     * @param int<0, max> $pollers
     */
    #[Pure]
    public function withMaxConcurrentNexusTaskPollers(int $pollers): self
    {
        \assert($pollers >= 0);

        $self = clone $this;
        $self->maxConcurrentNexusTaskPollers = $pollers;
        return $self;
    }

    /**
     * Optional: Enable logging in replay.
     *
     * In the workflow code you can use workflow.GetLogger(ctx) to write logs. By default, the logger will skip log
     * entry during replay mode so you won't see duplicate logs. This option will enable the logging in replay mode.
     * This is only useful for debugging purpose.
     */
    #[Pure]
    public function withEnableLoggingInReplay(bool $enable = true): self
    {
        $self = clone $this;
        $self->enableLoggingInReplay = $enable;
        return $self;
    }

    /**
     * Optional: Sticky schedule to start timeout.
     *
     * Sticky Execution is to run the workflow tasks for one workflow execution on same worker host. This is an
     * optimization for workflow execution. When sticky execution is enabled, worker keeps the workflow state in
     * memory. New workflow task contains the new history events will be dispatched to the same worker. If this
     * worker crashes, the sticky workflow task will timeout after StickyScheduleToStartTimeout, and temporal server
     * will clear the stickiness for that workflow execution and automatically reschedule a new workflow task that
     * is available for any worker to pick up and resume the progress.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     */
    #[Pure]
    public function withStickyScheduleToStartTimeout($timeout): self
    {
        \assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        \assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->stickyScheduleToStartTimeout = $timeout;
        return $self;
    }

    /**
     * Optional: Sets how workflow worker deals with non-deterministic history events
     * (presumably arising from non-deterministic workflow definitions or non-backward compatible workflow
     * definition changes) and other panics raised from workflow code.
     */
    #[Pure]
    public function withWorkflowPanicPolicy(WorkflowPanicPolicy $policy): self
    {
        $self = clone $this;
        $self->workflowPanicPolicy = $policy;
        return $self;
    }

    /**
     * Optional: worker graceful stop timeout.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     */
    #[Pure]
    public function withWorkerStopTimeout($timeout): self
    {
        \assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        \assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->workerStopTimeout = $timeout;
        return $self;
    }

    /**
     * Optional: Enable running session workers.
     *
     * Session workers is for activities within a session.
     * Enable this option to allow worker to process sessions.
     */
    #[Pure]
    public function withEnableSessionWorker(bool $enable = true): self
    {
        $self = clone $this;
        $self->enableSessionWorker = $enable;
        return $self;
    }

    /**
     * Optional: The identifier of the resource consumed by sessions.
     *
     * It's the user's responsibility to ensure there's only one worker using
     * this resourceID.
     *
     * For now, if user doesn't specify one, a new uuid will be used as the
     * resourceID.
     *
     * @param non-empty-string|null $identifier
     */
    #[Pure]
    public function withSessionResourceId(?string $identifier): self
    {
        $self = clone $this;
        $self->sessionResourceId = $identifier === '' ? null : $identifier;
        return $self;
    }

    /**
     * Optional: Sets the maximum number of concurrently running sessions the
     * resource support.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param int<0, max> $size
     */
    #[Pure]
    public function withMaxConcurrentSessionExecutionSize(int $size): self
    {
        \assert($size >= 0);

        $self = clone $this;
        $self->maxConcurrentSessionExecutionSize = $size;
        return $self;
    }

    /**
     * Optional: If set to true, a workflow worker is not started for this
     * worker and workflows cannot be registered with this worker. Use this if
     * you only want your worker to execute activities.
     */
    #[Pure]
    public function withDisableWorkflowWorker(bool $disable = true): self
    {
        $self = clone $this;
        $self->disableWorkflowWorker = $disable;
        return $self;
    }

    /**
     * Optional: If set to true worker would only handle workflow tasks and local activities.
     * Non-local activities will not be executed by this worker.
     */
    #[Pure]
    public function withLocalActivityWorkerOnly(bool $localOnly = true): self
    {
        $self = clone $this;
        $self->localActivityWorkerOnly = $localOnly;
        return $self;
    }

    /**
     * Optional: If set overwrites the client level Identify value.
     * default: client identity
     *
     * @param non-empty-string $identity
     */
    #[Pure]
    public function withIdentity(string $identity): self
    {
        $self = clone $this;
        $self->identity = $identity;
        return $self;
    }

    /**
     * Optional: If set defines maximum amount of time that workflow task will be allowed to run.
     * Default: 1 sec.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     */
    #[Pure]
    public function withDeadlockDetectionTimeout($timeout): self
    {
        \assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        \assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->deadlockDetectionTimeout = $timeout;
        return $self;
    }

    /**
     * Optional: The default amount of time between sending each pending heartbeat to the server.
     * This is used if the {@see ActivityOptions} do not provide a HeartbeatTimeout.
     * Otherwise, the interval becomes a value a bit smaller than the given HeartbeatTimeout.
     *
     * Default: 30 seconds
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $interval
     */
    #[Pure]
    public function withMaxHeartbeatThrottleInterval($interval): self
    {
        \assert(DateInterval::assert($interval));
        $interval = DateInterval::parse($interval, DateInterval::FORMAT_SECONDS);
        \assert($interval->totalMicroseconds >= 0);

        $self = clone $this;
        $self->maxHeartbeatThrottleInterval = $interval;
        return $self;
    }

    /**
     * Optional: Disable eager activities. If set to true, activities will not
     * be requested to execute eagerly from the same workflow regardless
     * of {@see self::$maxConcurrentEagerActivityExecutionSize}.
     *
     * Eager activity execution means the server returns requested eager
     * activities directly from the workflow task back to this worker which is
     * faster than non-eager which may be dispatched to a separate worker.
     *
     * @note Eager activities will automatically be disabled if {@see self::$taskQueueActivitiesPerSecond} is set.
     */
    #[Pure]
    public function withDisableEagerActivities(bool $disable = true): self
    {
        $self = clone $this;
        $self->disableEagerActivities = $disable;
        return $self;
    }

    /**
     * Optional: Maximum number of eager activities that can be running.
     *
     * When non-zero, eager activity execution will not be requested for
     * activities schedule by the workflow if it would cause the total number of
     * running eager activities to exceed this value. For example, if this is
     * set to 1000 and there are already 998 eager activities executing and a
     * workflow task schedules 3 more, only the first 2 will request eager
     * execution.
     *
     * The default of 0 means unlimited and therefore only bound by {@see self::$maxConcurrentActivityExecutionSize}.
     *
     * @see self::$disableEagerActivities for a description of eager activity execution.
     */
    #[Pure]
    public function withMaxConcurrentEagerActivityExecutionSize(int $size): self
    {
        \assert($size >= 0);

        $self = clone $this;
        $self->maxConcurrentEagerActivityExecutionSize = $size;
        return $self;
    }

    /**
     * Optional: Disable allowing workflow and activity functions that are
     * registered with custom names from being able to be called with their
     * function references.
     *
     * Users are strongly recommended to set this as true if they register any
     * workflow or activity functions with custom names. By leaving this as
     * false, the historical default, ambiguity can occur between function names
     * and aliased names when not using string names when executing child
     * workflow or activities.
     */
    #[Pure]
    public function withDisableRegistrationAliasing(bool $disable = true): self
    {
        $self = clone $this;
        $self->disableRegistrationAliasing = $disable;
        return $self;
    }

    /**
     * Assign a BuildID to this worker. This replaces the deprecated binary checksum concept,
     * and is used to provide a unique identifier for a set of worker code, and is necessary
     * to opt in to the Worker Versioning feature. See {@see self::$useBuildIDForVersioning}.
     *
     * @param non-empty-string $buildID
     *
     * @internal Experimental
     */
    #[Pure]
    public function withBuildID(string $buildID): self
    {
        $self = clone $this;
        $self->buildID = $buildID;
        return $self;
    }

    /**
     * Optional: If set, opts this worker into the Worker Versioning feature.
     * It will only operate on workflows it claims to be compatible with.
     * You must set {@see self::$buildID} if this flag is true.
     *
     * @internal Experimental
     * @note Cannot be enabled at the same time as {@see self::$enableSessionWorker}
     */
    #[Pure]
    public function withUseBuildIDForVersioning(bool $useBuildIDForVersioning = true): self
    {
        $self = clone $this;
        $self->useBuildIDForVersioning = $useBuildIDForVersioning;
        return $self;
    }
}
