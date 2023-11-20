<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Action;

use DateInterval;
use Google\Protobuf\Duration;
use Temporal\Api\Common\V1\Header;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Client\Schedule\ScheduleAction;
use Temporal\Client\Schedule\WorkflowIdReusePolicy;
use Temporal\Common\RetryOptions;
use Temporal\Common\TaskQueue\TaskQueue;
use Temporal\DataConverter\EncodedCollection;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\EncodedCollectionType;
use Temporal\Internal\Marshaller\Type\ObjectType;
use Temporal\Workflow\WorkflowType;

/**
 * @see \Temporal\Api\Workflow\V1\NewWorkflowExecutionInfo
 */
final class NewWorkflowExecutionInfo extends ScheduleAction
{
    /**
     * Workflow ID
     */
    #[Marshal(name: 'workflow_id')]
    public string $workflowId;

    /**
     * Workflow type
     */
    #[Marshal(name: 'workflow_type')]
    public WorkflowType $workflowType;

    /**
     * Task queue
     */
    #[Marshal(name: 'task_queue')]
    public TaskQueue $taskQueue;

    /**
     * Serialized arguments to the workflow
     */
    #[Marshal(type: ObjectType::class, of: EncodedValues::class)]
    public ?ValuesInterface $input;

    /**
     * Total workflow execution timeout including retries and continue as new
     */
    #[Marshal(name: 'workflow_execution_timeout', of: Duration::class)]
    public DateInterval $workflowExecutionTimeout;

    /**
     * Timeout of a single workflow run
     */
    #[Marshal(name: 'workflow_run_timeout', of: Duration::class)]
    public DateInterval $workflowRunTimeout;

    /**
     * Timeout of a single workflow task
     */
    #[Marshal(name: 'workflow_task_timeout', of: Duration::class)]
    public DateInterval $workflowTaskTimeout;

    /**
     * Default {@see WorkflowIdReusePolicy::WorkflowIdReusePolicyAllowDuplicate}
     */
    #[Marshal(name: 'workflow_id_reuse_policy')]
    public WorkflowIdReusePolicy $workflowIdReusePolicy;

    /**
     * The retry policy for the workflow. Will never exceed `workflow_execution_timeout`
     */
    #[Marshal(name: 'retry_policy')]
    public RetryOptions $retryPolicy;

    /**
     * @link https://docs.temporal.io/docs/content/what-is-a-temporal-cron-job/
     */
    #[Marshal(name: 'cron_schedule')]
    public string $cronSchedule;

    /**
     * Memo
     */
    #[Marshal(name: 'memo', type: EncodedCollectionType::class, of: Memo::class)]
    public EncodedCollection $memo;

    /**
     * Search attributes
     */
    #[Marshal(name: 'search_attributes', type: EncodedCollectionType::class, of: SearchAttributes::class)]
    public EncodedCollection $searchAttributes;

    /**
     * Header
     */
    #[Marshal(type: EncodedCollectionType::class, of: Header::class)]
    public EncodedCollection $header;
}
