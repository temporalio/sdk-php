<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\WorkflowOptions;
use Temporal\Testing\TimeSkippingWorkflowTestCase;

final class TimerOnlyTimeSkipTestCase extends TimeSkippingWorkflowTestCase
{
    public function testLongTimerSkipsInTime(): void
    {
        $stub = $this->workflowClient->newUntypedWorkflowStub(
            'LongTimerWorkflow',
            WorkflowOptions::new()->withTaskQueue('default'),
        );

        $before = \microtime(true);
        $serverBefore = $this->testingService->getCurrentTime()->getTimestamp();

        $run = $this->workflowClient->start($stub, 1800);
        $result = $run->getResult('string', 30);

        $timeElapsed = \microtime(true) - $before;
        $serverAfter = $this->testingService->getCurrentTime()->getTimestamp();

        self::assertSame('done', $result);
        self::assertGreaterThanOrEqual(1800, $serverAfter - $serverBefore);
        self::assertLessThan(20, $timeElapsed);
    }
}
