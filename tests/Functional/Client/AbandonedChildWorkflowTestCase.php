<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Testing\WithoutTimeSkipping;
use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\ParentWithAbandonedChildWorkflow;

final class AbandonedChildWorkflowTestCase extends WorkflowTestCase
{
    use WithoutTimeSkipping;

    public function testParentEndsWithoutWaitingForChild(): void
    {
        $timeBeforeStart = $this->testingService->getCurrentTime();
        /** @var ParentWithAbandonedChildWorkflow $parentWorkflow */
        $parentWorkflow = $this->workflowClient->newWorkflowStub(ParentWithAbandonedChildWorkflow::class);
        $parentWorkflow->start(10);
        $timeAfterStart = $this->testingService->getCurrentTime();
        static::assertTrue($timeAfterStart->diffInSeconds($timeBeforeStart) < 2);
    }
}

