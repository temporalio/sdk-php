<?php

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Temporal\Workflow\PendingActivityInfo;
use Temporal\Workflow\WorkflowExecutionConfig;
use Temporal\Workflow\WorkflowExecutionInfo;

/**
 * DTO that contains detailed information about Workflow Execution.
 *
 * @see \Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionResponse
 */
final class WorkflowExecutionDescription
{
    /**
     * @param list<PendingActivityInfo> $pendingActivities
     *
     * @internal
     */
    public function __construct(
        public readonly WorkflowExecutionConfig $config,
        public readonly WorkflowExecutionInfo $info,
        public readonly array $pendingActivities = [],
    ) {}
}
