<?php

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Temporal\Workflow\WorkflowExecutionConfig;
use Temporal\Workflow\WorkflowExecutionInfo;

/**
 * DTO that contains detailed information about Workflow Execution.
 *
 * @see \Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionResponse
 *
 * @internal
 */
final class WorkflowExecutionDescription
{
    /**
     * @internal
     */
    public function __construct(
        public readonly WorkflowExecutionConfig $config,
        public readonly WorkflowExecutionInfo $info,
    ) {}
}
