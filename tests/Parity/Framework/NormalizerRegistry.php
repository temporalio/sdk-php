<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework;

use Psr\Log\LoggerInterface;
use Temporal\Tests\Parity\Framework\Field\ActivityTypeNormalizer;
use Temporal\Tests\Parity\Framework\Field\BuildIdNormalizer;
use Temporal\Tests\Parity\Framework\Field\DurationNormalizer;
use Temporal\Tests\Parity\Framework\Field\IdentityNormalizer;
use Temporal\Tests\Parity\Framework\Field\SdkMetadataNormalizer;
use Temporal\Tests\Parity\Framework\Field\StackTraceNormalizer;
use Temporal\Tests\Parity\Framework\Field\TaskQueueNormalizer;
use Temporal\Tests\Parity\Framework\Field\TimestampNormalizer;
use Temporal\Tests\Parity\Framework\Field\WorkflowIdNormalizer;
use Temporal\Tests\Parity\Framework\Sdk\GoSdkNormalizer;
use Temporal\Tests\Parity\Framework\Sdk\JavaSdkNormalizer;
use Temporal\Tests\Parity\Framework\Sdk\PhpSdkNormalizer;

final class NormalizerRegistry
{
    public static function default(?LoggerInterface $logger = null): EventHistoryNormalizer
    {
        $timestamp = new TimestampNormalizer($logger);
        $duration = new DurationNormalizer($logger);
        $id = new WorkflowIdNormalizer($logger);
        $identity = new IdentityNormalizer($logger);
        $taskQueue = new TaskQueueNormalizer($logger);
        $buildId = new BuildIdNormalizer($logger);
        $sdkMetadata = new SdkMetadataNormalizer($logger);
        $stackTrace = new StackTraceNormalizer($logger);
        $activityType = new ActivityTypeNormalizer($logger);

        // Leaf event-key → field normalizer. Add new keys here when a future scenario
        // surfaces a non-deterministic field. `taskQueue` is keyed at the map level
        // (not the bare `name` key) so `workflowType.name` survives.
        $sharedRules = [
            'eventTime' => $timestamp,
            'workflowExecutionExpirationTime' => $timestamp,
            'firstWorkflowTaskBackoff' => $duration,
            'workflowExecutionTimeout' => $duration,
            'workflowRunTimeout' => $duration,
            'workflowTaskTimeout' => $duration,
            'scheduleToCloseTimeout' => $duration,
            'scheduleToStartTimeout' => $duration,
            'startToCloseTimeout' => $duration,
            'heartbeatTimeout' => $duration,
            'backoffStartInterval' => $duration,
            'eventId' => $id,
            'taskId' => $id,
            'scheduledEventId' => $id,
            'startedEventId' => $id,
            'requestId' => $id,
            'workflowId' => $id,
            'runId' => $id,
            'firstExecutionRunId' => $id,
            'originalExecutionRunId' => $id,
            'continuedExecutionRunId' => $id,
            'newExecutionRunId' => $id,
            'timerId' => $id,
            'activityId' => $id,
            'workflowTaskCompletedEventId' => $id,
            'identity' => $identity,
            'buildId' => $buildId,
            'sdkMetadata' => $sdkMetadata,
            'taskQueue' => $taskQueue,
            'stackTrace' => $stackTrace,
            'message' => $stackTrace,
            'historySizeBytes' => $id,
            'activityType' => $activityType,
        ];

        return new EventHistoryNormalizer([
            Source::PHP->value => new PhpSdkNormalizer($sharedRules, $logger),
            Source::JAVA->value => new JavaSdkNormalizer($sharedRules, $logger),
            Source::GO->value => new GoSdkNormalizer($sharedRules, $logger),
        ]);
    }
}
