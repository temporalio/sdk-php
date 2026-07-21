<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\WorkflowOptions;
use Temporal\Testing\TimeSkippingWorkflowTestCase;

final class WorkflowSideDelayedCallbackTestCase extends TimeSkippingWorkflowTestCase
{
    public function testDeferredActionsFireAtTheirVirtualTimeWithoutRealWaiting(): void
    {
        $stub = $this->workflowClient->newUntypedWorkflowStub(
            'DelayedCallbackWorkflow',
            WorkflowOptions::new()
                ->withTaskQueue('default')
                ->withWorkflowExecutionTimeout(new \DateInterval('PT2H')),
        );

        $before = \microtime(true);
        $serverBefore = $this->testingService->getCurrentTime()->getTimestamp();

        $run = $this->workflowClient->start($stub, [[300, 'a'], [600, 'b'], [1800, 'c']]);
        $fired = $run->getResult('array', 30);

        $elapsed = \microtime(true) - $before;
        $serverElapsed = $this->testingService->getCurrentTime()->getTimestamp() - $serverBefore;

        self::assertArrayHasKey('a', $fired);
        self::assertArrayHasKey('b', $fired);
        self::assertArrayHasKey('c', $fired);
        self::assertSame(300, $fired['b'] - $fired['a']);
        self::assertSame(1500, $fired['c'] - $fired['a']);
        self::assertGreaterThanOrEqual(1800, $serverElapsed);
        self::assertLessThan(20, $elapsed);
    }
}
