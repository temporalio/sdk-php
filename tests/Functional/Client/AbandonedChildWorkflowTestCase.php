<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Carbon\Carbon;
use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\ParentWithAbandonedChildWorkflow;

final class AbandonedChildWorkflowTestCase extends WorkflowTestCase
{
    public function testParentEndsWithoutWaitingForChild(): void
    {
        $timeBeforeStart = Carbon::now();
        $parentWorkflow = $this->workflowClient->newWorkflowStub(ParentWithAbandonedChildWorkflow::class);
        $run = $this->workflowClient->start($parentWorkflow, 5, false);
        static::assertSame('Welcome from parent', $run->getResult());
        $timeAfterStart = Carbon::now();
        static::assertTrue($timeAfterStart->diffInSeconds($timeBeforeStart) < 2);
    }

    public function testParentCanWaitForChildResult(): void
    {
        $parentWorkflow = $this->workflowClient->newWorkflowStub(ParentWithAbandonedChildWorkflow::class);
        $run = $this->workflowClient->start($parentWorkflow, 3, true);
        static::assertSame('Hello from child', $run->getResult());
    }
}

