<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Testing\WorkflowTestCase;

final class SchedulerWithMockedActivityTestCase extends WorkflowTestCase
{
    public function testScheduledSignalThenMockedActivityDoesNotHitStartToClose(): void
    {
        $this->activityMocks->expectCompletion('SimpleActivity.echo', 'mocked-echo');

        $stub = $this->workflowClient->newUntypedWorkflowStub(
            'SignalThenMockedActivityWorkflow',
            WorkflowOptions::new()
                ->withTaskQueue('default')
                ->withWorkflowExecutionTimeout(new \DateInterval('PT2H')),
        );

        $scheduler = $this->delayedCallbacks;
        $scheduler->readyWhen(static fn(): bool => (bool) $stub->query('ready')?->getValue(0, 'bool'));
        $scheduler->registerDelayedCallback(1800, static fn(WorkflowStubInterface $s) => $s->signal('go'));

        $before = \microtime(true);
        $serverBefore = $this->testingService->getCurrentTime()->getTimestamp();

        $scheduler->start($stub);
        $result = $stub->getResult('string', 30);

        $elapsed = \microtime(true) - $before;
        $serverElapsed = $this->testingService->getCurrentTime()->getTimestamp() - $serverBefore;

        self::assertSame('mocked-echo', $result);
        self::assertGreaterThanOrEqual(1800, $serverElapsed);
        self::assertLessThan(20, $elapsed);
    }
}
