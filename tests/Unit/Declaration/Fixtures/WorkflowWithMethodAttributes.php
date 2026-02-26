<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixtures;

use Temporal\Common\CronSchedule;
use Temporal\Workflow\Attribute\TaskQueue;
use Temporal\Workflow\Attribute\WorkflowExecutionTimeout;

class WorkflowWithMethodAttributes
{
    #[TaskQueue('method-wf-queue')]
    #[WorkflowExecutionTimeout(7200)]
    #[CronSchedule('0 */2 * * *')]
    public function execute(): void
    {
    }
}
