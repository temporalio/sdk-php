<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Testing\WorkflowTestCase;

final class ClientSideDelayedCallbackTestCase extends WorkflowTestCase
{
    public function testCallbacksFireAtScheduledSimulatedTimes(): void
    {
        $stub = $this->workflowClient->newUntypedWorkflowStub(
            'SignalCollectorWorkflow',
            WorkflowOptions::new()
                ->withTaskQueue('default')
                ->withWorkflowExecutionTimeout(new \DateInterval('PT2H')),
        );

        $scheduler = $this->delayedCallbacks;
        $scheduler->registerDelayedCallback(300, static fn(WorkflowStubInterface $s) => $s->signal('push', 'first'));
        $scheduler->registerDelayedCallback(600, static fn(WorkflowStubInterface $s) => $s->signal('push', 'second'));
        $scheduler->registerDelayedCallback(900, static fn(WorkflowStubInterface $s) => $s->signal('finish'));

        $before = \microtime(true);
        $base = $this->testingService->getCurrentTime()->getTimestamp();

        $scheduler->start($stub, 3600);
        $events = $stub->getResult('array', 30);

        $elapsed = \microtime(true) - $before;

        self::assertCount(2, $events);
        self::assertSame('first', $events[0]['value']);
        self::assertSame('second', $events[1]['value']);
        self::assertSame(300, $events[1]['at'] - $events[0]['at']);
        self::assertGreaterThanOrEqual(300, $events[0]['at'] - $base);
        self::assertLessThan(20, $elapsed);
    }
}
