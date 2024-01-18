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
     * Optional: To set the maximum concurrent activity executions this worker
     * can have.
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
     */
    #[Marshal(name: 'MaxConcurrentWorkflowTaskExecutionSize')]
    public int $maxConcurrentWorkflowTaskExecutionSize = 0;

    /**
     * Optional: Sets the maximum number of goroutines that will concurrently
     * poll the temporal-server to retrieve workflow tasks. Changing this value
     * will affect the rate at which the worker is able to consume tasks from
     * a task queue.
     */
    #[Marshal(name: 'MaxConcurrentWorkflowTaskPollers')]
    public int $maxConcurrentWorkflowTaskPollers = 0;

    /**
     * Optional: Sticky schedule to start timeout.
     *
     * The resolution is seconds. See details about StickyExecution on the
     * comments for DisableStickyExecution.
     */
    #[Marshal(name: 'StickyScheduleToStartTimeout', type: NullableType::class, of: DateIntervalType::class)]
    public ?\DateInterval $stickyScheduleToStartTimeout = null;

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
     * Optional: Sets the maximum number of concurrently running sessions the
     * resource support.
     */
    #[Marshal(name: 'MaxConcurrentSessionExecutionSize')]
    public int $maxConcurrentSessionExecutionSize = 1000;

    /**
     * @return static
     */
    #[Pure]
    public static function new(): self
    {
        return new self();
    }

    /**
     * Optional: To set the maximum concurrent activity executions this worker
     * can have.
     *
     * The zero value of this uses the default value.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param int<0, max> $size
     * @return self
     */
    #[Pure]
    public function withMaxConcurrentActivityExecutionSize(int $size): self
    {
        assert($size >= 0);

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
     *
     * @param float $interval
     * @return self
     */
    #[Pure]
    public function withWorkerActivitiesPerSecond(float $interval): self
    {
        assert($interval >= 0);

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
     * @return self
     */
    #[Pure]
    public function withMaxConcurrentLocalActivityExecutionSize(int $size): self
    {
        assert($size >= 0);

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
     *
     * @param float $interval
     * @return self
     */
    #[Pure]
    public function withWorkerLocalActivitiesPerSecond(float $interval): self
    {
        assert($interval >= 0);

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
     * @psalm-suppress ImpureMethodCall
     *
     * @param float $interval
     * @return self
     */
    #[Pure]
    public function withTaskQueueActivitiesPerSecond(float $interval): self
    {
        assert($interval >= 0);

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
     * @return self
     */
    #[Pure]
    public function withMaxConcurrentActivityTaskPollers(int $pollers): self
    {
        assert($pollers >= 0);

        $self = clone $this;

        $self->maxConcurrentActivityTaskPollers = $pollers;

        return $self;
    }

    /**
     * Optional: To set the maximum concurrent workflow task executions this
     * worker can have.
     *
     * The zero value of this uses the default value.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param int<0, max> $size
     * @return self
     */
    #[Pure]
    public function withMaxConcurrentWorkflowTaskExecutionSize(int $size): self
    {
        assert($size >= 0);

        $self = clone $this;

        $self->maxConcurrentWorkflowTaskExecutionSize = $size;

        return $self;
    }

    /**
     * Optional: Sets the maximum number of goroutines that will concurrently
     * poll the temporal-server to retrieve workflow tasks. Changing this value
     * will affect the rate at which the worker is able to consume tasks from
     * a task queue.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param int<0, max> $pollers
     * @return self
     */
    #[Pure]
    public function withMaxConcurrentWorkflowTaskPollers(int $pollers): self
    {
        assert($pollers >= 0);

        $self = clone $this;

        $self->maxConcurrentWorkflowTaskPollers = $pollers;

        return $self;
    }

    /**
     * Optional: Sticky schedule to start timeout.
     *
     * The resolution is seconds. See details about StickyExecution on the
     * comments for DisableStickyExecution.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     * @return self
     */
    #[Pure]
    public function withStickyScheduleToStartTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->stickyScheduleToStartTimeout = $timeout;
        return $self;
    }

    /**
     * Optional: worker graceful stop timeout.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     * @return self
     */
    #[Pure]
    public function withWorkerStopTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->workerStopTimeout = $timeout;
        return $self;
    }

    /**
     * Optional: Enable running session workers.
     *
     * Session workers is for activities within a session.
     * Enable this option to allow worker to process sessions.
     *
     * @param bool $enable
     * @return self
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
     * @param string|null $identifier
     * @return self
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
     * @return self
     */
    #[Pure]
    public function withMaxConcurrentSessionExecutionSize(int $size): self
    {
        assert($size >= 0);

        $self = clone $this;

        $self->maxConcurrentSessionExecutionSize = $size;

        return $self;
    }
}
