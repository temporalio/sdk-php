<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Coresdk\Activity_task\Start;
use Coresdk\Workflow_activation\InitializeWorkflow;
use Temporal\Activity\ActivityInfo;
use Temporal\Activity\ActivityType;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Priority;
use Temporal\Api\Common\V1\RetryPolicy;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Common\Priority as PriorityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Common\TypedSearchAttributes;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInfo;
use Temporal\Workflow\WorkflowType;

/**
 * @internal
 */
final class InfoFactory
{
    private const FLOAT32_SIGNIFICANT_DIGITS = '%.7g';

    public function __construct(
        private readonly PayloadMapper $payloads,
    ) {}

    /**
     * @psalm-suppress InaccessibleProperty, ArgumentTypeCoercion, PropertyTypeCoercion
     */
    public function workflowInfo(InitializeWorkflow $init, string $runId, string $namespace, string $taskQueue): WorkflowInfo
    {
        $parent = $init->getParentWorkflowInfo();
        $root = $init->getRootWorkflow();

        $info = (new \ReflectionClass(WorkflowInfo::class))->newInstanceWithoutConstructor();
        $info->execution = new WorkflowExecution($init->getWorkflowId(), $runId);
        $info->type = new WorkflowType();
        $info->type->name = $init->getWorkflowType();
        $info->taskQueue = $taskQueue;
        $info->executionTimeout = ProtoTime::interval($init->getWorkflowExecutionTimeout());
        $info->runTimeout = ProtoTime::interval($init->getWorkflowRunTimeout());
        $info->taskTimeout = ProtoTime::interval($init->getWorkflowTaskTimeout());
        $info->namespace = $namespace;
        $info->attempt = $init->getAttempt();
        $info->cronSchedule = $init->getCronSchedule() ?: null;
        $info->continuedExecutionRunId = $init->getContinuedFromExecutionRunId();
        $info->firstExecutionRunId = $init->getFirstExecutionRunId();
        $info->originalExecutionRunId = $init->getOriginalExecutionRunId() ?: $runId;
        $info->parentNamespace = $parent?->getNamespace() ?? '';
        $info->parentExecution = self::execution($parent?->getWorkflowId(), $parent?->getRunId());
        $info->rootExecution = self::execution($root?->getWorkflowId(), $root?->getRunId());
        $info->typedSearchAttributes = TypedSearchAttributes::empty();
        if ($init->hasSearchAttributes()) {
            /** @var SearchAttributes $searchAttributes */
            $searchAttributes = $init->getSearchAttributes();
            $info->searchAttributes = $this->payloads->decodeCollection($searchAttributes->getIndexedFields());
            $info->typedSearchAttributes = TypedSearchAttributes::fromJsonArray($this->typedSearchAttributes($searchAttributes));
        }
        if ($init->hasMemo()) {
            /** @var Memo $memo */
            $memo = $init->getMemo();
            $info->memo = $this->payloads->decodeCollection($memo->getFields());
        }
        $info->retryOptions = self::retryOptions($init->getRetryPolicy());
        $info->priority = self::priorityOptions($init->getPriority());

        return $info;
    }

    /**
     * @psalm-suppress InaccessibleProperty, ArgumentTypeCoercion
     */
    public function activityInfo(string $token, Start $start, string $taskQueue): ActivityInfo
    {
        $scheduledTime = ProtoTime::micros($start->getScheduledTime());
        $startedTime = ProtoTime::micros($start->getStartedTime());

        $info = (new \ReflectionClass(ActivityInfo::class))->newInstanceWithoutConstructor();
        $info->taskToken = $token;
        $info->workflowType = new WorkflowType();
        $info->workflowType->name = $start->getWorkflowType();
        $info->workflowNamespace = $start->getWorkflowNamespace();
        $info->workflowExecution = new WorkflowExecution(
            $start->getWorkflowExecution()?->getWorkflowId() ?? '',
            $start->getWorkflowExecution()?->getRunId() ?? '',
        );
        $info->id = $start->getActivityId();
        $info->type = new ActivityType();
        $info->type->name = $start->getActivityType();
        $info->taskQueue = $taskQueue;
        $info->heartbeatTimeout = ProtoTime::interval($start->getHeartbeatTimeout());
        $info->scheduledTime = ProtoTime::utc($scheduledTime);
        $info->startedTime = ProtoTime::utc($startedTime);
        $info->deadline = ProtoTime::utc(self::deadline($start, $scheduledTime, $startedTime));
        $info->attempt = $start->getAttempt();
        $info->priority = self::priorityOptions($start->getPriority());
        $info->retryOptions = self::retryOptions($start->getRetryPolicy());

        return $info;
    }

    private static function deadline(Start $start, int $scheduledTime, int $startedTime): int
    {
        $scheduleToClose = ProtoTime::microsOf($start->getScheduleToCloseTimeout());
        $startToClose = ProtoTime::microsOf($start->getStartToCloseTimeout());

        if ($scheduleToClose <= 0) {
            return $startedTime + $startToClose;
        }
        if ($startToClose <= 0) {
            return $scheduledTime + $scheduleToClose;
        }

        return \min($scheduledTime + $scheduleToClose, $startedTime + $startToClose);
    }

    /**
     * @psalm-suppress InaccessibleProperty, PropertyTypeCoercion
     */
    private static function retryOptions(?RetryPolicy $retry): ?RetryOptions
    {
        if ($retry === null) {
            return null;
        }

        $options = (new \ReflectionClass(RetryOptions::class))->newInstanceWithoutConstructor();
        $options->initialInterval = ProtoTime::interval($retry->getInitialInterval());
        $options->backoffCoefficient = $retry->getBackoffCoefficient();
        $options->maximumInterval = ProtoTime::interval($retry->getMaximumInterval());
        $options->maximumAttempts = $retry->getMaximumAttempts();
        $options->nonRetryableExceptions = \iterator_to_array($retry->getNonRetryableErrorTypes());

        return $options;
    }

    private static function priorityOptions(?Priority $priority): PriorityOptions
    {
        $options = PriorityOptions::new($priority?->getPriorityKey() ?? 0);
        $options->fairnessKey = $priority?->getFairnessKey() ?? '';
        $options->fairnessWeight = self::float32AsJson($priority?->getFairnessWeight() ?? 0.0);

        return $options;
    }

    private static function float32AsJson(float $value): float
    {
        return (float) \sprintf(self::FLOAT32_SIGNIFICANT_DIGITS, $value);
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    private static function execution(?string $workflowId, ?string $runId): ?WorkflowExecution
    {
        return ($workflowId ?? '') ? new WorkflowExecution($workflowId, (string) $runId) : null;
    }

    private function typedSearchAttributes(SearchAttributes $attributes): array
    {
        $result = [];
        foreach ($attributes->getIndexedFields() as $name => $payload) {
            $type = $payload->getMetadata()['type'] ?? null;
            if ($type !== null) {
                $result[$name] = ['type' => $type, 'value' => $this->payloads->decode($payload)];
            }
        }

        return $result;
    }
}
