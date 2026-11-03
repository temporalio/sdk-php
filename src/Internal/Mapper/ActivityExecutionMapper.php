<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Mapper;

use Temporal\Api\Activity\V1\ActivityExecutionInfo;
use Temporal\Api\Activity\V1\ActivityExecutionListInfo;
use Temporal\Api\Common\V1\Payload;
use Temporal\Client\Activity\ActivityExecutionDescription;
use Temporal\Client\Activity\ActivityExecutionInfo as ActivityExecutionInfoDto;
use Temporal\Client\Activity\ActivityExecutionStatus;
use Temporal\Client\Workflow\UserMetadata;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedCollection;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Internal\Support\DateInterval;
use Temporal\Workflow\PendingActivityState;

/**
 * @internal
 */
final class ActivityExecutionMapper
{
    public function __construct(
        private readonly DataConverterInterface $converter,
        private readonly string $namespace,
    ) {}

    public function fromInfo(ActivityExecutionInfo $message): ActivityExecutionDescription
    {
        $context = new ActivitySerializationContext(
            namespace: $this->namespace,
            activityType: $message->getActivityType()?->getName() ?? '',
            taskQueue: $message->getTaskQueue(),
        );
        $pendingMapper = new PendingActivityInfoMapper($this->converter, $this->namespace);
        $heartbeatDetails = $message->getHeartbeatDetails();
        $failure = $message->getLastFailure();
        $metadata = $message->getUserMetadata();
        $retryPolicy = $message->getRetryPolicy();

        return new ActivityExecutionDescription(
            info: $this->fromListInfo($message),
            runState: PendingActivityState::from($message->getRunState()),
            attempt: $message->getAttempt(),
            scheduleToCloseTimeout: DateInterval::parseOrNull($message->getScheduleToCloseTimeout()),
            scheduleToStartTimeout: DateInterval::parseOrNull($message->getScheduleToStartTimeout()),
            startToCloseTimeout: DateInterval::parseOrNull($message->getStartToCloseTimeout()),
            heartbeatTimeout: DateInterval::parseOrNull($message->getHeartbeatTimeout()),
            startDelay: DateInterval::parseOrNull($message->getStartDelay()),
            retryPolicy: $retryPolicy === null ? null : $pendingMapper->prepareRetryPolicy($retryPolicy),
            priority: $pendingMapper->preparePriority($message->getPriority()),
            heartbeatDetails: $heartbeatDetails === null
                ? EncodedValues::empty()
                : EncodedValues::fromPayloads($heartbeatDetails, $this->converter)->withSerializationContext($context),
            totalHeartbeatCount: (int) $message->getTotalHeartbeatCount(),
            lastHeartbeatTime: $message->getLastHeartbeatTime()?->toDateTime(),
            lastStartedTime: $message->getLastStartedTime()?->toDateTime(),
            lastAttemptCompleteTime: $message->getLastAttemptCompleteTime()?->toDateTime(),
            nextAttemptScheduleTime: $message->getNextAttemptScheduleTime()?->toDateTime(),
            expirationTime: $message->getExpirationTime()?->toDateTime(),
            currentRetryInterval: DateInterval::parseOrNull($message->getCurrentRetryInterval()),
            lastFailure: $failure === null
                ? null
                : FailureConverter::mapFailureToException($failure, $this->converter, $context),
            lastWorkerIdentity: $message->getLastWorkerIdentity(),
            lastDeploymentVersion: $pendingMapper->prepareDeploymentVersion($message->getLastDeploymentVersion()),
            canceledReason: $message->getCanceledReason(),
            userMetadata: new UserMetadata(
                $this->decodeString($metadata?->getSummary()),
                $this->decodeString($metadata?->getDetails()),
            ),
        );
    }

    public function fromListInfo(ActivityExecutionListInfo|ActivityExecutionInfo $message): ActivityExecutionInfoDto
    {
        $searchAttributes = $message->getSearchAttributes();

        return new ActivityExecutionInfoDto(
            activityId: $message->getActivityId(),
            runId: $message->getRunId(),
            activityType: $message->getActivityType()?->getName() ?? '',
            status: ActivityExecutionStatus::from($message->getStatus()),
            taskQueue: $message->getTaskQueue(),
            scheduleTime: $message->getScheduleTime()?->toDateTime(),
            executionTime: $message->getExecutionTime()?->toDateTime(),
            closeTime: $message->getCloseTime()?->toDateTime(),
            executionDuration: DateInterval::parseOrNull($message->getExecutionDuration()),
            searchAttributes: $searchAttributes === null
                ? EncodedCollection::fromValues([], $this->converter)
                : EncodedCollection::fromPayloadCollection($searchAttributes->getIndexedFields(), $this->converter),
        );
    }

    private function decodeString(?Payload $payload): string
    {
        return $payload === null ? '' : $this->converter->fromPayload($payload, 'string');
    }
}
