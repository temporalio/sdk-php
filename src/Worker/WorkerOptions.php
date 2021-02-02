<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

// todo: map worker options
class WorkerOptions
{
//    // Optional: To set the maximum concurrent activity executions this worker can have.
//    // The zero value of this uses the default value.
//    // default: defaultMaxConcurrentActivityExecutionSize(1k)
    //MaxConcurrentActivityExecutionSize int = 0
//
//    // Optional: Sets the rate limiting on number of activities that can be executed per second per
//    // worker. This can be used to limit resources used by the worker.
//    // Notice that the number is represented in float, so that you can set it to less than
//    // 1 if needed. For example, set the number to 0.1 means you want your activity to be executed
//    // once for every 10 seconds. This can be used to protect down stream services from flooding.
//    // The zero value of this uses the default value
//    // default: 100k
    //WorkerActivitiesPerSecond float64 = 0
//
//    // Optional: To set the maximum concurrent local activity executions this worker can have.
//    // The zero value of this uses the default value.
//    // default: 1k
    //MaxConcurrentLocalActivityExecutionSize int = 0
//
//    // Optional: Sets the rate limiting on number of local activities that can be executed per second per
//    // worker. This can be used to limit resources used by the worker.
//    // Notice that the number is represented in float, so that you can set it to less than
//    // 1 if needed. For example, set the number to 0.1 means you want your local activity to be executed
//    // once for every 10 seconds. This can be used to protect down stream services from flooding.
//    // The zero value of this uses the default value
//    // default: 100k
    //WorkerLocalActivitiesPerSecond float64 = 0
//
//    // Optional: Sets the rate limiting on number of activities that can be executed per second.
//    // This is managed by the server and controls activities per second for your entire taskqueue
//    // whereas WorkerActivityTasksPerSecond controls activities only per worker.
//    // Notice that the number is represented in float, so that you can set it to less than
//    // 1 if needed. For example, set the number to 0.1 means you want your activity to be executed
//    // once for every 10 seconds. This can be used to protect down stream services from flooding.
//    // The zero value of this uses the default value.
//    // default: 100k
    //TaskQueueActivitiesPerSecond float64 = 0
//
//    // Optional: Sets the maximum number of goroutines that will concurrently poll the
//    // temporal-server to retrieve activity tasks. Changing this value will affect the
//    // rate at which the worker is able to consume tasks from a task queue.
//    // default: 2
    //MaxConcurrentActivityTaskPollers int =0
//
//    // Optional: To set the maximum concurrent workflow task executions this worker can have.
//    // The zero value of this uses the default value.
//    // default: defaultMaxConcurrentTaskExecutionSize(1k)
    //MaxConcurrentWorkflowTaskExecutionSize int =0
//
//    // Optional: Sets the maximum number of goroutines that will concurrently poll the
//    // temporal-server to retrieve workflow tasks. Changing this value will affect the
//    // rate at which the worker is able to consume tasks from a task queue.
//    // default: 2
    //MaxConcurrentWorkflowTaskPollers int =0
//
//    // Optional: Sticky schedule to start timeout.
//    // The resolution is seconds. See details about StickyExecution on the comments for DisableStickyExecution.
//    // default: 5s
    //StickyScheduleToStartTimeout time.Duration = 0

//    // Optional: worker graceful stop timeout
//    // default: 0s
    //WorkerStopTimeout time.Duration = 0
//
//    // Optional: Enable running session workers.
//    // Session workers is for activities within a session.
//    // Enable this option to allow worker to process sessions.
//    // default: false
    //EnableSessionWorker bool = false
//
//    // Uncomment this option when we support automatic restablish failed sessions.
//    // Optional: The identifier of the resource consumed by sessions.
//    // It's the user's responsibility to ensure there's only one worker using this resourceID.
//    // For now, if user doesn't specify one, a new uuid will be used as the resourceID.
//    // SessionResourceID string
//
//    // Optional: Sets the maximum number of concurrently running sessions the resource support.
//    // default: 1000
    //MaxConcurrentSessionExecutionSize int = 1000

    public static function new(): self
    {
        return new self();
    }
}
