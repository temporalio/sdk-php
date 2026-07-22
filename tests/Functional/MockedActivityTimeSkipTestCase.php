<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\WorkflowOptions;
use Temporal\Testing\TimeSkippingWorkflowTestCase;

final class MockedActivityTimeSkipTestCase extends TimeSkippingWorkflowTestCase
{
    public function testMockedActivityAfterLongTimerDoesNotHitStartToCloseTimeout(): void
    {
        $this->activityMocks->expectCompletion('SimpleActivity.echo', 'mocked-echo');

        $stub = $this->workflowClient->newUntypedWorkflowStub(
            'TimerThenMockedActivityWorkflow',
            WorkflowOptions::new()
                ->withTaskQueue('default')
                ->withWorkflowExecutionTimeout(new \DateInterval('PT2H')),
        );

        $timeBefore = \microtime(true);
        $serverBefore = $this->testingService->getCurrentTime()->getTimestamp();

        $run = $this->workflowClient->start($stub, 1800);
        $result = $run->getResult('string', 30);

        $timeElapsed = \microtime(true) - $timeBefore;
        $serverAfter = $this->testingService->getCurrentTime()->getTimestamp();

        self::assertSame('mocked-echo', $result);
        self::assertGreaterThanOrEqual(1800, $serverAfter - $serverBefore);
        self::assertLessThan(20, $timeElapsed);
    }
}
