<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixtures;

use Temporal\Common\IdReusePolicy;
use Temporal\Common\Priority;
use Temporal\Common\RetryOptions;
use Temporal\Common\WorkflowIdConflictPolicy;
use Temporal\Workflow\Attribute\Memo;
use Temporal\Workflow\Attribute\SearchAttributes;
use Temporal\Workflow\Attribute\Summary;
use Temporal\Workflow\Attribute\TaskQueue;
use Temporal\Workflow\Attribute\WorkflowExecutionTimeout;
use Temporal\Workflow\Attribute\WorkflowIdConflictPolicy as WorkflowIdConflictPolicyAttr;
use Temporal\Workflow\Attribute\WorkflowIdReusePolicy;
use Temporal\Workflow\Attribute\WorkflowRunTimeout;
use Temporal\Workflow\Attribute\WorkflowStartDelay;
use Temporal\Workflow\Attribute\WorkflowTaskTimeout;

#[TaskQueue('workflow-queue')]
#[WorkflowExecutionTimeout(3600)]
#[WorkflowRunTimeout(1800)]
#[WorkflowTaskTimeout(30)]
#[WorkflowStartDelay(10)]
#[WorkflowIdReusePolicy(IdReusePolicy::RejectDuplicate)]
#[WorkflowIdConflictPolicyAttr(WorkflowIdConflictPolicy::TerminateExisting)]
#[RetryOptions]
#[Memo(['key1' => 'value1'])]
#[SearchAttributes(['CustomField' => 'search-value'])]
#[Priority(priorityKey: 3)]
#[Summary('Important workflow')]
class WorkflowWithAllAttributes
{
}
