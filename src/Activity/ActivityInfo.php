<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use JetBrains\PhpStorm\Immutable;
use Temporal\Activity;
use Temporal\Client\ActivityCompletionClientInterface;
use Temporal\Common\Uuid;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\DateTimeType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Marshaller\Type\ObjectType;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowType;

/**
 * ActivityInfo contains information about currently executing activity.
 * Use {@see Activity::getInfo()} to access.
 */
#[Immutable]
final class ActivityInfo
{
    /**
     * A correlation token that can be used to complete the activity through `complete` method.
     *
     * @see ActivityCompletionClientInterface::complete()
     */
    #[Marshal(name: 'TaskToken')]
    public string $taskToken;

    #[Marshal(name: 'WorkflowType', type: NullableType::class, of: WorkflowType::class)]
    public ?WorkflowType $workflowType = null;

    #[Marshal(name: 'WorkflowNamespace')]
    public string $workflowNamespace = 'default';

    #[Marshal(name: 'WorkflowExecution', type: NullableType::class, of: WorkflowExecution::class)]
    public ?WorkflowExecution $workflowExecution = null;

    /**
     * An ID of the activity. This identifier can be used to complete the
     * activity through `complete` method.
     *
     * @see ActivityCompletionClientInterface::complete()
     */
    #[Marshal(name: 'ActivityID')]
    public string $id;

    /**
     * Type (name) of the activity.
     */
    #[Marshal(name: 'ActivityType', type: ObjectType::class, of: ActivityType::class)]
    public ActivityType $type;

    #[Marshal(name: 'TaskQueue')]
    public string $taskQueue = WorkerFactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * Maximum time between heartbeats. 0 means no heartbeat needed.
     */
    #[Marshal(name: 'HeartbeatTimeout', type: DateIntervalType::class)]
    public \DateInterval $heartbeatTimeout;

    /**
     * Time of activity scheduled by a workflow
     */
    #[Marshal(name: 'ScheduledTime', type: DateTimeType::class)]
    public \DateTimeInterface $scheduledTime;

    /**
     * Time of activity start
     */
    #[Marshal(name: 'StartedTime', type: DateTimeType::class)]
    public \DateTimeInterface $startedTime;

    /**
     * Time of activity timeout
     */
    #[Marshal(name: 'Deadline', type: DateTimeType::class)]
    public \DateTimeInterface $deadline;

    /**
     * Attempt starts from 1, and increased by 1 for every retry if
     * retry policy is specified.
     */
    #[Marshal(name: 'Attempt')]
    public int $attempt = 1;

    /**
     * ActivityInfo constructor.
     */
    public function __construct()
    {
        $this->id = '0';
        $this->taskToken = \base64_encode(Uuid::nil());
        $this->type = new ActivityType();

        $this->heartbeatTimeout = CarbonInterval::second(0);
        $this->scheduledTime = CarbonImmutable::now();
        $this->startedTime = CarbonImmutable::now();
        $this->deadline = CarbonImmutable::now();
    }
}
