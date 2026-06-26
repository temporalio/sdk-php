<?php

declare(strict_types=1);

namespace Temporal\Internal\Mapper;

use Temporal\Activity\ActivityType as ActivityTypeDto;
use Temporal\Api\Activity\V1\ActivityOptions as ActivityOptionsMessage;
use Temporal\Api\Common\V1\Priority as PriorityMessage;
use Temporal\Api\Common\V1\RetryPolicy;
use Temporal\Api\Deployment\V1\WorkerDeploymentVersion as WorkerDeploymentVersionMessage;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Workflow\V1\PendingActivityInfo;
use Temporal\Api\Workflow\V1\PendingActivityInfo\PauseInfo;
use Temporal\Common\Priority as PriorityDto;
use Temporal\Common\Versioning\WorkerDeploymentVersion;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Exception\Failure\TemporalFailure;
use Temporal\Internal\Support\DateInterval;
use Temporal\Workflow\PendingActivityInfo as PendingActivityInfoDto;
use Temporal\Workflow\PendingActivityOptions;
use Temporal\Workflow\PendingActivityPauseInfo;
use Temporal\Workflow\PendingActivityPauseInfoManual;
use Temporal\Workflow\PendingActivityPauseInfoRule;
use Temporal\Workflow\PendingActivityRetryPolicy;
use Temporal\Workflow\PendingActivityState;

final class PendingActivityInfoMapper
{
    public function __construct(
        private readonly DataConverterInterface $converter,
        private readonly string $namespace,
        private readonly ?string $workflowId = null,
    ) {}

    /**
     * @psalm-suppress DocblockTypeContradiction, RedundantConditionGivenDocblockType
     */
    public function fromMessage(PendingActivityInfo $message): PendingActivityInfoDto
    {
        $activityType = new ActivityTypeDto();
        /** @psalm-suppress InaccessibleProperty */
        $activityType->name = $message->getActivityType()?->getName() ?? '';

        $retryInterval = $message->getCurrentRetryInterval();
        $retryInterval === null or $retryInterval = DateInterval::parse($retryInterval);

        $serializationContext = new ActivitySerializationContext(
            namespace: $this->namespace,
            workflowId: $this->workflowId,
            activityType: $message->getActivityType()?->getName(),
        );

        return new PendingActivityInfoDto(
            activityId: $message->getActivityId(),
            activityType: $activityType,
            state: PendingActivityState::from($message->getState()),
            heartbeatDetails: $this->prepareHeartbeatDetails($message, $serializationContext),
            lastHeartbeatTime: $message->getLastHeartbeatTime()?->toDateTime(),
            lastStartedTime: $message->getLastStartedTime()?->toDateTime(),
            attempt: $message->getAttempt(),
            maximumAttempts: $message->getMaximumAttempts(),
            scheduledTime: $message->getScheduledTime()?->toDateTime(),
            expirationTime: $message->getExpirationTime()?->toDateTime(),
            lastFailure: $this->prepareFailure($message->getLastFailure(), $serializationContext),
            lastWorkerIdentity: $message->getLastWorkerIdentity(),
            currentRetryInterval: $retryInterval,
            lastAttemptCompleteTime: $message->getLastAttemptCompleteTime()?->toDateTime(),
            nextAttemptScheduleTime: $message->getNextAttemptScheduleTime()?->toDateTime(),
            paused: $message->getPaused(),
            lastDeploymentVersion: $this->prepareDeploymentVersion($message->getLastDeploymentVersion()),
            priority: $this->preparePriority($message->getPriority()),
            pauseInfo: $this->preparePauseInfo($message->getPauseInfo()),
            activityOptions: $this->prepareActivityOptions($message->getActivityOptions()),
        );
    }

    private function prepareHeartbeatDetails(
        PendingActivityInfo $message,
        ActivitySerializationContext $context,
    ): ValuesInterface {
        $details = $message->getHeartbeatDetails();
        if ($details === null) {
            return EncodedValues::empty();
        }

        $values = EncodedValues::fromPayloads($details, $this->converter);
        $values->setSerializationContext($context);

        return $values;
    }

    private function prepareFailure(?Failure $failure, ActivitySerializationContext $context): ?TemporalFailure
    {
        if ($failure === null) {
            return null;
        }

        $exception = FailureConverter::mapFailureToException($failure, $this->converter);
        $exception->setSerializationContext($context);

        return $exception;
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    private function prepareDeploymentVersion(?WorkerDeploymentVersionMessage $version): ?WorkerDeploymentVersion
    {
        return $version === null
            ? null
            : WorkerDeploymentVersion::new($version->getDeploymentName(), $version->getBuildId());
    }

    private function preparePriority(?PriorityMessage $priority): ?PriorityDto
    {
        if ($priority === null) {
            return null;
        }

        $result = PriorityDto::new($priority->getPriorityKey())
            ->withFairnessKey($priority->getFairnessKey());

        $weight = $priority->getFairnessWeight();
        $weight >= 0.001 && $weight <= 1000.0 and $result = $result->withFairnessWeight($weight);

        return $result;
    }

    private function preparePauseInfo(?PauseInfo $pauseInfo): ?PendingActivityPauseInfo
    {
        if ($pauseInfo === null) {
            return null;
        }

        $manual = $pauseInfo->getManual();
        $rule = $pauseInfo->getRule();

        return new PendingActivityPauseInfo(
            pauseTime: $pauseInfo->getPauseTime()?->toDateTime(),
            manual: $manual === null
                ? null
                : new PendingActivityPauseInfoManual($manual->getIdentity(), $manual->getReason()),
            rule: $rule === null
                ? null
                : new PendingActivityPauseInfoRule($rule->getRuleId(), $rule->getIdentity(), $rule->getReason()),
        );
    }

    /**
     * @psalm-suppress DocblockTypeContradiction, RedundantConditionGivenDocblockType
     */
    private function prepareActivityOptions(?ActivityOptionsMessage $options): ?PendingActivityOptions
    {
        if ($options === null) {
            return null;
        }

        $scheduleToClose = $options->getScheduleToCloseTimeout();
        $scheduleToStart = $options->getScheduleToStartTimeout();
        $startToClose = $options->getStartToCloseTimeout();
        $heartbeat = $options->getHeartbeatTimeout();
        $retryPolicy = $options->getRetryPolicy();

        return new PendingActivityOptions(
            taskQueue: $options->getTaskQueue()?->getName(),
            scheduleToCloseTimeout: $scheduleToClose === null ? null : DateInterval::parse($scheduleToClose),
            scheduleToStartTimeout: $scheduleToStart === null ? null : DateInterval::parse($scheduleToStart),
            startToCloseTimeout: $startToClose === null ? null : DateInterval::parse($startToClose),
            heartbeatTimeout: $heartbeat === null ? null : DateInterval::parse($heartbeat),
            retryPolicy: $retryPolicy === null ? null : $this->prepareRetryPolicy($retryPolicy),
        );
    }

    /**
     * @psalm-suppress DocblockTypeContradiction, RedundantConditionGivenDocblockType, TooManyTemplateParams
     */
    private function prepareRetryPolicy(RetryPolicy $policy): PendingActivityRetryPolicy
    {
        $initialInterval = $policy->getInitialInterval();
        $maximumInterval = $policy->getMaximumInterval();

        $nonRetryableErrorTypes = [];
        foreach ($policy->getNonRetryableErrorTypes() as $errorType) {
            $nonRetryableErrorTypes[] = $errorType;
        }

        return new PendingActivityRetryPolicy(
            initialInterval: $initialInterval === null ? null : DateInterval::parse($initialInterval),
            backoffCoefficient: $policy->getBackoffCoefficient(),
            maximumInterval: $maximumInterval === null ? null : DateInterval::parse($maximumInterval),
            maximumAttempts: $policy->getMaximumAttempts(),
            nonRetryableErrorTypes: $nonRetryableErrorTypes,
        );
    }
}
