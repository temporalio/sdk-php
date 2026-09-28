<?php

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Temporal\Internal\Traits\CloneWith;
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
    use CloneWith;

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

    /**
     * @param list<PendingActivityInfo> $pendingActivities
     */
    public function withPendingActivities(array $pendingActivities): self
    {
        /** @see self::$pendingActivities */
        return $this->cloneWith('pendingActivities', $pendingActivities);
    }
}
