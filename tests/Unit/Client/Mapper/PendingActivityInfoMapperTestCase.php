<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\Mapper;

use Google\Protobuf\Duration;
use Google\Protobuf\Timestamp;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Activity\V1\ActivityOptions as ActivityOptionsMessage;
use Temporal\Api\Common\V1\ActivityType;
use Temporal\Api\Common\V1\Priority as PriorityMessage;
use Temporal\Api\Common\V1\RetryPolicy;
use Temporal\Api\Deployment\V1\WorkerDeploymentVersion as WorkerDeploymentVersionMessage;
use Temporal\Api\Enums\V1\PendingActivityState as PendingActivityStateEnum;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Taskqueue\V1\TaskQueue as TaskQueueMessage;
use Temporal\Api\Workflow\V1\PendingActivityInfo;
use Temporal\Api\Workflow\V1\PendingActivityInfo\PauseInfo;
use Temporal\Api\Workflow\V1\PendingActivityInfo\PauseInfo\Manual;
use Temporal\Api\Workflow\V1\PendingActivityInfo\PauseInfo\Rule;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\Type;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Internal\Mapper\PendingActivityInfoMapper;
use Temporal\Workflow\PendingActivityState;

final class PendingActivityInfoMapperTestCase extends TestCase
{
    public function testFromMessageFullyPopulated(): void
    {
        $converter = DataConverter::createDefault();
        $mapper = new PendingActivityInfoMapper($converter, 'default', 'wf-1');

        $info = $mapper->fromMessage(
            new PendingActivityInfo([
                'activity_id' => 'act-1',
                'activity_type' => new ActivityType(['name' => 'MyActivity']),
                'state' => PendingActivityStateEnum::PENDING_ACTIVITY_STATE_PAUSED,
                'heartbeat_details' => EncodedValues::fromValues(['hb-progress'], $converter)->toPayloads(),
                'last_heartbeat_time' => new Timestamp(['seconds' => \strtotime('2021-01-01T00:00:00.000000Z')]),
                'last_started_time' => new Timestamp(['seconds' => \strtotime('2021-01-02T00:00:00.000000Z')]),
                'attempt' => 3,
                'maximum_attempts' => 7,
                'scheduled_time' => new Timestamp(['seconds' => \strtotime('2021-01-03T00:00:00.000000Z')]),
                'expiration_time' => new Timestamp(['seconds' => \strtotime('2021-01-04T00:00:00.000000Z')]),
                'last_failure' => (new Failure())
                    ->setMessage('boom')
                    ->setApplicationFailureInfo((new ApplicationFailureInfo())->setType('MyError')),
                'last_worker_identity' => 'worker-42',
                'current_retry_interval' => (new Duration())->setSeconds(5),
                'last_attempt_complete_time' => new Timestamp(['seconds' => \strtotime('2021-01-05T00:00:00.000000Z')]),
                'next_attempt_schedule_time' => new Timestamp(['seconds' => \strtotime('2021-01-06T00:00:00.000000Z')]),
                'paused' => true,
                'last_deployment_version' => (new WorkerDeploymentVersionMessage())
                    ->setDeploymentName('dep')
                    ->setBuildId('bid'),
                'priority' => (new PriorityMessage())
                    ->setPriorityKey(3)
                    ->setFairnessKey('tenant')
                    ->setFairnessWeight(0.5),
                'pause_info' => (new PauseInfo())
                    ->setPauseTime(new Timestamp(['seconds' => \strtotime('2021-01-07T00:00:00.000000Z')]))
                    ->setManual((new Manual())->setIdentity('admin')->setReason('maintenance')),
                'activity_options' => (new ActivityOptionsMessage())
                    ->setTaskQueue((new TaskQueueMessage())->setName('my-tq'))
                    ->setScheduleToCloseTimeout((new Duration())->setSeconds(10))
                    ->setScheduleToStartTimeout((new Duration())->setSeconds(2))
                    ->setStartToCloseTimeout((new Duration())->setSeconds(8))
                    ->setHeartbeatTimeout((new Duration())->setSeconds(1))
                    ->setRetryPolicy(
                        (new RetryPolicy())
                            ->setInitialInterval((new Duration())->setSeconds(1))
                            ->setBackoffCoefficient(2.0)
                            ->setMaximumInterval((new Duration())->setSeconds(50))
                            ->setMaximumAttempts(5)
                            ->setNonRetryableErrorTypes(['TypeA', 'TypeB']),
                    ),
            ]),
        );

        self::assertSame('act-1', $info->activityId);
        self::assertSame('MyActivity', $info->activityType->name);
        self::assertSame(PendingActivityState::Paused, $info->state);
        self::assertSame(['hb-progress'], $info->heartbeatDetails->getValues());
        self::assertSame('2021-01-01T00:00:00.000000Z', $info->lastHeartbeatTime->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame('2021-01-02T00:00:00.000000Z', $info->lastStartedTime->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame(3, $info->attempt);
        self::assertSame(7, $info->maximumAttempts);
        self::assertSame('2021-01-03T00:00:00.000000Z', $info->scheduledTime->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame('2021-01-04T00:00:00.000000Z', $info->expirationTime->format('Y-m-d\TH:i:s.u\Z'));

        self::assertInstanceOf(ApplicationFailure::class, $info->lastFailure);
        self::assertSame('MyError', $info->lastFailure->getType());

        self::assertSame('worker-42', $info->lastWorkerIdentity);
        self::assertSame(5, $info->currentRetryInterval->s);
        self::assertSame('2021-01-05T00:00:00.000000Z', $info->lastAttemptCompleteTime->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame('2021-01-06T00:00:00.000000Z', $info->nextAttemptScheduleTime->format('Y-m-d\TH:i:s.u\Z'));
        self::assertTrue($info->paused);

        self::assertNotNull($info->lastDeploymentVersion);
        self::assertSame('dep', $info->lastDeploymentVersion->deploymentName);
        self::assertSame('bid', $info->lastDeploymentVersion->buildId);

        self::assertNotNull($info->priority);
        self::assertSame(3, $info->priority->priorityKey);
        self::assertSame('tenant', $info->priority->fairnessKey);
        self::assertSame(0.5, $info->priority->fairnessWeight);

        self::assertNotNull($info->pauseInfo);
        self::assertSame('2021-01-07T00:00:00.000000Z', $info->pauseInfo->pauseTime->format('Y-m-d\TH:i:s.u\Z'));
        self::assertNotNull($info->pauseInfo->manual);
        self::assertSame('admin', $info->pauseInfo->manual->identity);
        self::assertSame('maintenance', $info->pauseInfo->manual->reason);
        self::assertNull($info->pauseInfo->rule);

        self::assertNotNull($info->activityOptions);
        self::assertSame('my-tq', $info->activityOptions->taskQueue);
        self::assertSame(10, $info->activityOptions->scheduleToCloseTimeout->s);
        self::assertSame(2, $info->activityOptions->scheduleToStartTimeout->s);
        self::assertSame(8, $info->activityOptions->startToCloseTimeout->s);
        self::assertSame(1, $info->activityOptions->heartbeatTimeout->s);

        self::assertNotNull($info->activityOptions->retryPolicy);
        self::assertSame(1, $info->activityOptions->retryPolicy->initialInterval->s);
        self::assertSame(2.0, $info->activityOptions->retryPolicy->backoffCoefficient);
        self::assertSame(50, $info->activityOptions->retryPolicy->maximumInterval->s);
        self::assertSame(5, $info->activityOptions->retryPolicy->maximumAttempts);
        self::assertSame(['TypeA', 'TypeB'], $info->activityOptions->retryPolicy->nonRetryableErrorTypes);
    }

    public function testFromMessageMinimal(): void
    {
        $mapper = new PendingActivityInfoMapper(DataConverter::createDefault(), 'default', 'wf-1');

        $info = $mapper->fromMessage(new PendingActivityInfo());

        self::assertSame('', $info->activityId);
        self::assertSame('', $info->activityType->name);
        self::assertSame(PendingActivityState::Unspecified, $info->state);
        self::assertSame([], $info->heartbeatDetails->getValues());
        self::assertNull($info->lastHeartbeatTime);
        self::assertNull($info->lastStartedTime);
        self::assertSame(0, $info->attempt);
        self::assertSame(0, $info->maximumAttempts);
        self::assertNull($info->scheduledTime);
        self::assertNull($info->expirationTime);
        self::assertNull($info->lastFailure);
        self::assertSame('', $info->lastWorkerIdentity);
        self::assertNull($info->currentRetryInterval);
        self::assertNull($info->lastAttemptCompleteTime);
        self::assertNull($info->nextAttemptScheduleTime);
        self::assertFalse($info->paused);
        self::assertNull($info->lastDeploymentVersion);
        self::assertNull($info->priority);
        self::assertNull($info->pauseInfo);
        self::assertNull($info->activityOptions);
    }

    public function testPauseInfoByRule(): void
    {
        $mapper = new PendingActivityInfoMapper(DataConverter::createDefault(), 'default', 'wf-1');

        $info = $mapper->fromMessage(
            new PendingActivityInfo([
                'pause_info' => (new PauseInfo())
                    ->setRule(
                        (new Rule())
                            ->setRuleId('rule-1')
                            ->setIdentity('system')
                            ->setReason('flaky activity'),
                    ),
            ]),
        );

        self::assertNotNull($info->pauseInfo);
        self::assertNull($info->pauseInfo->pauseTime);
        self::assertNull($info->pauseInfo->manual);
        self::assertNotNull($info->pauseInfo->rule);
        self::assertSame('rule-1', $info->pauseInfo->rule->ruleId);
        self::assertSame('system', $info->pauseInfo->rule->identity);
        self::assertSame('flaky activity', $info->pauseInfo->rule->reason);
    }

    public function testHeartbeatDetailsDecodeWithActivityContext(): void
    {
        $converter = new DataConverter(new ActivityContextSigningConverter());

        $signed = EncodedValues::fromValues(['hb-progress'], $converter);
        $signed->setSerializationContext(new ActivitySerializationContext(
            namespace: 'default',
            workflowId: 'wf-1',
            activityType: 'MyActivity',
            taskQueue: 'my-tq',
        ));

        $mapper = new PendingActivityInfoMapper($converter, 'default', 'wf-1');
        $info = $mapper->fromMessage(new PendingActivityInfo([
            'activity_type' => new ActivityType(['name' => 'MyActivity']),
            'activity_options' => (new ActivityOptionsMessage())
                ->setTaskQueue((new TaskQueueMessage())->setName('my-tq')),
            'heartbeat_details' => $signed->toPayloads(),
        ]));

        self::assertSame('hb-progress', $info->heartbeatDetails->getValue(0, Type::TYPE_STRING));
    }
}

final class ActivityContextSigningConverter implements PayloadConverterInterface, SerializationContextAwareInterface
{
    private const ENCODING = 'act-signed';

    private ?SerializationContext $context = null;

    public function withSerializationContext(?SerializationContext $context): static
    {
        $clone = clone $this;
        $clone->context = $context;
        return $clone;
    }

    public function getEncodingType(): string
    {
        return self::ENCODING;
    }

    public function toPayload($value): ?Payload
    {
        if (!\is_string($value)) {
            return null;
        }

        return (new Payload())
            ->setMetadata(['encoding' => self::ENCODING, 'signature' => $this->signature()])
            ->setData($value);
    }

    public function fromPayload(Payload $payload, Type $type): mixed
    {
        $metadata = $payload->getMetadata();
        $actual = $metadata['signature'] ?? '';
        $expected = $this->signature();

        if ($actual !== $expected) {
            throw new \RuntimeException(
                \sprintf('Signature mismatch: expected "%s", got "%s"', $expected, $actual),
            );
        }

        return $payload->getData();
    }

    private function signature(): string
    {
        return $this->context instanceof ActivitySerializationContext
            ? (string) $this->context->workflowId
                . ':' . (string) $this->context->activityType
                . ':' . (string) $this->context->taskQueue
            : '';
    }
}
