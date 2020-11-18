<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Temporal\Client\Activity\Info\ActivityType;
use Temporal\Client\Worker\FactoryInterface;
use Temporal\Client\Workflow\Info\WorkflowExecution;
use Temporal\Client\Workflow\Info\WorkflowType;

final class ActivityInfo
{
    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    public string $taskToken;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var WorkflowType|null
     */
    public ?WorkflowType $workflowType = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    public string $workflowNamespace = 'default';

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var WorkflowExecution|null
     */
    public ?WorkflowExecution $workflowExecution = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    public string $id;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var ActivityType
     */
    public ActivityType $type;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    public string $taskQueue = FactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * Maximum time between heartbeats. 0 means no heartbeat needed.
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    public \DateInterval $heartbeatTimeout;

    /**
     * Time of activity scheduled by a workflow
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateTimeInterface
     */
    public \DateTimeInterface $scheduledTime;

    /**
     * Time of activity start
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateTimeInterface
     */
    public \DateTimeInterface $startedTime;

    /**
     * Time of activity timeout
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateTimeInterface
     */
    public \DateTimeInterface $deadline;

    /**
     * Attempt starts from 1, and increased by 1 for every retry if
     * retry policy is specified.
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var int
     */
    public int $attempt = 1;

    /**
     * @param string $taskToken
     * @param string $id
     * @param ActivityType $type
     */
    public function __construct(
        string $taskToken,
        string $id,
        ActivityType $type
    ) {
        $this->taskToken = $taskToken;
        $this->id = $id;
        $this->type = $type;

        $this->heartbeatTimeout = CarbonInterval::second(0);
        $this->scheduledTime = Carbon::now();
        $this->startedTime = Carbon::now();
        $this->deadline = Carbon::now();
    }

    /**
     * TODO throw exception in case of incorrect data, not really since it driven by the server
     *
     * @param array $info
     * @return static
     * @throws \Exception
     */
    public static function fromArray(array $info): self
    {
        $instance = new self($info['TaskToken'], $info['ActivityID'], ActivityType::fromArray($info['ActivityType']));

        $instance->workflowType = WorkflowType::fromArray($info['WorkflowType']);
        $instance->workflowNamespace = $info['WorkflowNamespace'];
        $instance->workflowExecution = WorkflowExecution::fromArray($info['WorkflowExecution']);
        $instance->taskQueue = $info['TaskQueue'];
        $instance->heartbeatTimeout = CarbonInterval::microseconds($info['HeartbeatTimeout']);
        $instance->scheduledTime = Carbon::parse($info['ScheduledTime']);
        $instance->startedTime = Carbon::parse($info['StartedTime']);
        $instance->deadline = Carbon::parse($info['Deadline']);
        $instance->attempt = $info['Attempt'];

        return $instance;
    }
}
