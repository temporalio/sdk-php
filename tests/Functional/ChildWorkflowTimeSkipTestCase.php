<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\WorkflowOptions;
use Temporal\Testing\TimeSkippingWorkflowTestCase;

final class ChildWorkflowTimeSkipTestCase extends TimeSkippingWorkflowTestCase
{
    public function testParentWaitingOnChildLongTimerSkipsTime(): void
    {
        $stub = $this->workflowClient->newUntypedWorkflowStub(
            'ParentWaitsChildTimerWorkflow',
            WorkflowOptions::new()->withTaskQueue('default'),
        );

        $timeBefore = \microtime(true);
        $serverBefore = $this->testingService->getCurrentTime()->getTimestamp();

        $run = $this->workflowClient->start($stub, 1800);
        $result = $run->getResult('string', 30);

        $timeElapsed = \microtime(true) - $timeBefore;
        $serverAfter = $this->testingService->getCurrentTime()->getTimestamp();

        self::assertSame('done', $result);
        self::assertGreaterThanOrEqual(1800, $serverAfter - $serverBefore);
        self::assertLessThan(20, $timeElapsed);
    }
}
