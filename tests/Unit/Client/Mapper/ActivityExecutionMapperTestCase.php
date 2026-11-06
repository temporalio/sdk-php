<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\Mapper;

use Google\Protobuf\Duration;
use Google\Protobuf\Timestamp;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Activity\V1\ActivityExecutionInfo;
use Temporal\Api\Activity\V1\ActivityExecutionListInfo;
use Temporal\Api\Common\V1\ActivityType;
use Temporal\Api\Common\V1\Priority as PriorityMessage;
use Temporal\Api\Common\V1\RetryPolicy;
use Temporal\Api\Deployment\V1\WorkerDeploymentVersion as WorkerDeploymentVersionMessage;
use Temporal\Api\Enums\V1\ActivityExecutionStatus as ActivityExecutionStatusEnum;
use Temporal\Api\Enums\V1\PendingActivityState as PendingActivityStateEnum;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Sdk\V1\UserMetadata;
use Temporal\Client\Activity\ActivityExecutionStatus;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Internal\Mapper\ActivityExecutionMapper;
use Temporal\Workflow\PendingActivityState;

final class ActivityExecutionMapperTestCase extends TestCase
{
    public function testFromInfo(): void
    {
        $converter = DataConverter::createDefault();
        $mapper = new ActivityExecutionMapper($converter, 'default');

        $description = $mapper->fromInfo(new ActivityExecutionInfo([
            'activity_id' => 'act-1',
            'run_id' => 'run-1',
            'activity_type' => new ActivityType(['name' => 'MyActivity']),
            'status' => ActivityExecutionStatusEnum::ACTIVITY_EXECUTION_STATUS_RUNNING,
            'run_state' => PendingActivityStateEnum::PENDING_ACTIVITY_STATE_STARTED,
            'task_queue' => 'queue',
            'schedule_to_close_timeout' => (new Duration())->setSeconds(60),
            'start_to_close_timeout' => (new Duration())->setSeconds(30),
            'heartbeat_timeout' => (new Duration())->setSeconds(5),
            'start_delay' => (new Duration())->setSeconds(2),
            'retry_policy' => (new RetryPolicy())->setMaximumAttempts(4)->setBackoffCoefficient(2.0),
            'priority' => (new PriorityMessage())->setPriorityKey(3),
            'heartbeat_details' => EncodedValues::fromValues(['progress'], $converter)->toPayloads(),
            'total_heartbeat_count' => 7,
            'attempt' => 2,
            'schedule_time' => new Timestamp(['seconds' => \strtotime('2021-01-01T00:00:00Z')]),
            'execution_time' => new Timestamp(['seconds' => \strtotime('2021-01-01T00:00:02Z')]),
            'last_attempt_complete_time' => new Timestamp(['seconds' => \strtotime('2021-01-01T00:00:10Z')]),
            'next_attempt_schedule_time' => new Timestamp(['seconds' => \strtotime('2021-01-01T00:00:20Z')]),
            'expiration_time' => new Timestamp(['seconds' => \strtotime('2021-01-01T00:01:00Z')]),
            'current_retry_interval' => (new Duration())->setSeconds(10),
            'last_failure' => (new Failure())
                ->setMessage('boom')
                ->setApplicationFailureInfo((new ApplicationFailureInfo())->setType('MyError')),
            'last_worker_identity' => 'worker-1',
            'last_deployment_version' => new WorkerDeploymentVersionMessage([
                'deployment_name' => 'deployment',
                'build_id' => 'build',
            ]),
            'canceled_reason' => 'stop',
            'user_metadata' => new UserMetadata([
                'summary' => $converter->toPayload('summary'),
                'details' => $converter->toPayload('details'),
            ]),
        ]));

        self::assertSame('act-1', $description->info->activityId);
        self::assertSame('run-1', $description->info->runId);
        self::assertSame('MyActivity', $description->info->activityType);
        self::assertSame(ActivityExecutionStatus::Running, $description->info->status);
        self::assertSame('2021-01-01T00:00:02+00:00', $description->info->executionTime?->format(\DATE_ATOM));
        self::assertSame(PendingActivityState::Started, $description->runState);
        self::assertSame(2, $description->attempt);
        self::assertSame(60, (int) $description->scheduleToCloseTimeout?->totalSeconds);
        self::assertNull($description->scheduleToStartTimeout);
        self::assertSame(30, (int) $description->startToCloseTimeout?->totalSeconds);
        self::assertSame(5, (int) $description->heartbeatTimeout?->totalSeconds);
        self::assertSame(2, (int) $description->startDelay?->totalSeconds);
        self::assertSame(4, $description->retryPolicy?->maximumAttempts);
        self::assertSame(3, $description->priority?->priorityKey);
        self::assertSame('progress', $description->heartbeatDetails->getValue(0, 'string'));
        self::assertSame(7, $description->totalHeartbeatCount);
        self::assertSame('2021-01-01T00:00:10+00:00', $description->lastAttemptCompleteTime?->format(\DATE_ATOM));
        self::assertSame('2021-01-01T00:00:20+00:00', $description->nextAttemptScheduleTime?->format(\DATE_ATOM));
        self::assertSame('2021-01-01T00:01:00+00:00', $description->expirationTime?->format(\DATE_ATOM));
        self::assertSame(10, (int) $description->currentRetryInterval?->totalSeconds);
        self::assertInstanceOf(ApplicationFailure::class, $description->lastFailure);
        self::assertSame('worker-1', $description->lastWorkerIdentity);
        self::assertSame('deployment', $description->lastDeploymentVersion?->deploymentName);
        self::assertSame('build', $description->lastDeploymentVersion?->buildId);
        self::assertSame('stop', $description->canceledReason);
        self::assertSame('summary', $description->userMetadata->summary);
        self::assertSame('details', $description->userMetadata->details);
    }

    public function testUnknownEnumValuesBecomeUnspecified(): void
    {
        $description = (new ActivityExecutionMapper(DataConverter::createDefault(), 'default'))->fromInfo(
            (new ActivityExecutionInfo())->setStatus(999)->setRunState(999),
        );

        self::assertSame(ActivityExecutionStatus::Unspecified, $description->info->status);
        self::assertSame(PendingActivityState::Unspecified, $description->runState);
    }

    public function testFromListInfo(): void
    {
        $mapper = new ActivityExecutionMapper(DataConverter::createDefault(), 'default');

        $info = $mapper->fromListInfo(new ActivityExecutionListInfo([
            'activity_id' => 'act-1',
            'run_id' => 'run-1',
            'activity_type' => new ActivityType(['name' => 'MyActivity']),
            'status' => ActivityExecutionStatusEnum::ACTIVITY_EXECUTION_STATUS_COMPLETED,
            'task_queue' => 'queue',
            'execution_duration' => (new Duration())->setSeconds(3),
        ]));

        self::assertSame('act-1', $info->activityId);
        self::assertSame(ActivityExecutionStatus::Completed, $info->status);
        self::assertSame('queue', $info->taskQueue);
        self::assertSame(3, (int) $info->executionDuration?->totalSeconds);
        self::assertNull($info->closeTime);
        self::assertSame(0, $info->searchAttributes->count());
    }
}
