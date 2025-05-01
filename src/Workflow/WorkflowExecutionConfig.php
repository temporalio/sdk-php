<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use JetBrains\PhpStorm\Immutable;
use Temporal\Client\Workflow\UserMetadata;
use Temporal\Common\TaskQueue\TaskQueue;

/**
 * DTO that contains basic information about Workflow Execution.
 *
 * @see \Temporal\Api\Workflow\V1\WorkflowExecutionConfig
 * @psalm-immutable
 */
#[Immutable]
final class WorkflowExecutionConfig
{
    /**
     * @internal
     */
    public function __construct(
        public readonly TaskQueue $taskQueue,
        public readonly ?\DateInterval $workflowExecutionTimeout,
        public readonly ?\DateInterval $workflowRunTimeout,
        public readonly ?\DateInterval $defaultWorkflowTaskTimeout,
        public readonly UserMetadata $userMetadata,
    ) {}
}
