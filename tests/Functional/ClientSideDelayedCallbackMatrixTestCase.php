<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Testing\WorkflowTestCase;

final class ClientSideDelayedCallbackMatrixTestCase extends WorkflowTestCase
{
    public function testRejectsNegativeDelay(): void
    {
        $scheduler = $this->delayedCallbacks;

        $this->expectException(\InvalidArgumentException::class);
        $scheduler->registerDelayedCallback(-1, static fn(WorkflowStubInterface $s) => null);
    }

    public function testFiresInSortedOrderRegardlessOfRegistrationOrder(): void
    {
        $stub = $this->collector();
        $scheduler = $this->delayedCallbacks;
        $scheduler->registerDelayedCallback(600, static fn(WorkflowStubInterface $s) => $s->signal('push', 'second'));
        $scheduler->registerDelayedCallback(300, static fn(WorkflowStubInterface $s) => $s->signal('push', 'first'));
        $scheduler->registerDelayedCallback(900, static fn(WorkflowStubInterface $s) => $s->signal('finish'));

        $scheduler->start($stub, 3600);
        $events = $stub->getResult('array', 30);

        self::assertSame(['first', 'second'], \array_column($events, 'value'));
    }

    public function testZeroDelayFiresImmediately(): void
    {
        $stub = $this->collector();
        $scheduler = $this->delayedCallbacks;
        $scheduler->registerDelayedCallback(0, static fn(WorkflowStubInterface $s) => $s->signal('push', 'zero'));
        $scheduler->registerDelayedCallback(1, static fn(WorkflowStubInterface $s) => $s->signal('finish'));

        $scheduler->start($stub, 3600);
        $events = $stub->getResult('array', 30);

        self::assertSame(['zero'], \array_column($events, 'value'));
    }

    public function testQueryCallbackReadsStateAtScheduledTime(): void
    {
        $stub = $this->collector();
        $countAt600 = null;
        $scheduler = $this->delayedCallbacks;
        $scheduler->registerDelayedCallback(300, static fn(WorkflowStubInterface $s) => $s->signal('push', 'a'));
        $scheduler->registerDelayedCallback(600, static function (WorkflowStubInterface $s) use (&$countAt600): void {
            $countAt600 = $s->query('count')?->getValue(0, 'int');
        });
        $scheduler->registerDelayedCallback(900, static fn(WorkflowStubInterface $s) => $s->signal('finish'));

        $scheduler->start($stub, 3600);
        $stub->getResult('array', 30);

        self::assertSame(1, $countAt600);
    }

    public function testCancelCallbackCancelsWorkflowAtScheduledTime(): void
    {
        $stub = $this->collector();
        $scheduler = $this->delayedCallbacks;
        $scheduler->registerDelayedCallback(300, static fn(WorkflowStubInterface $s) => $s->cancel());

        $scheduler->start($stub, 3600);

        $this->expectException(WorkflowFailedException::class);
        $stub->getResult('array', 30);
    }

    public function testEmptySchedulerLeavesCounterBalanced(): void
    {
        $stub = $this->collector();
        $scheduler = $this->delayedCallbacks;

        $scheduler->start($stub, 5);
        $events = $stub->getResult('array', 30);

        self::assertSame([], $events);
        self::assertSame(0, $this->testingService->lockDelta());
    }

    public function testLateCallbackFailsWithClearError(): void
    {
        $stub = $this->collector();
        $scheduler = $this->delayedCallbacks;
        $scheduler->registerDelayedCallback(300, static fn(WorkflowStubInterface $s) => $s->signal('finish'));
        $scheduler->registerDelayedCallback(600, static fn(WorkflowStubInterface $s) => $s->signal('push', 'late'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no longer running/');
        $scheduler->start($stub, 3600);
    }

    public function testNoTimerWorkflowDrivenViaReadyWhen(): void
    {
        $stub = $this->workflowClient->newUntypedWorkflowStub(
            'SignalOnlyWorkflow',
            WorkflowOptions::new()->withTaskQueue('default'),
        );

        $scheduler = $this->delayedCallbacks;
        $scheduler->readyWhen(static fn(): bool => (bool) $stub->query('started')?->getValue(0, 'bool'));
        $scheduler->registerDelayedCallback(300, static fn(WorkflowStubInterface $s) => $s->signal('ping'));
        $scheduler->registerDelayedCallback(600, static fn(WorkflowStubInterface $s) => $s->signal('ping'));
        $scheduler->registerDelayedCallback(900, static fn(WorkflowStubInterface $s) => $s->signal('finish'));

        $scheduler->start($stub);
        $received = $stub->getResult('int', 30);

        self::assertSame(2, $received);
    }

    private function collector(): WorkflowStubInterface
    {
        return $this->workflowClient->newUntypedWorkflowStub(
            'SignalCollectorWorkflow',
            WorkflowOptions::new()
                ->withTaskQueue('default')
                ->withWorkflowExecutionTimeout(new \DateInterval('PT2H')),
        );
    }
}
