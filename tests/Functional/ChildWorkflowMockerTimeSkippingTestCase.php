<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Testing\WorkflowMocker;
use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\LongTimerWorkflow;
use Temporal\Tests\Workflow\ParentWithChildAndTimerWorkflow;

final class ChildWorkflowMockerTimeSkippingTestCase extends WorkflowTestCase
{
    private const TIMER_SECONDS = 1800;
    private const MAX_WALL_CLOCK_SECONDS = 60;

    private ?WorkflowMocker $childWorkflowMocks = null;

    public function testParentWithStubbedChildSkipsThirtyMinuteTimerWithoutRealWaiting(): void
    {
        $this->childWorkflowMocks()->expectCompletion('LongTimerWorkflow', 'stubbed child');

        $serverTimeBefore = $this->testingService->getCurrentTime();
        $wallClockBefore = \microtime(true);

        $parent = $this->workflowClient->newWorkflowStub(ParentWithChildAndTimerWorkflow::class);
        $run = $this->workflowClient->start($parent);
        $result = $run->getResult('string', self::MAX_WALL_CLOCK_SECONDS);

        $wallClockElapsed = \microtime(true) - $wallClockBefore;
        $serverTimeElapsed = $this->testingService->getCurrentTime()->getTimestamp() - $serverTimeBefore->getTimestamp();

        self::assertSame('parent: stubbed child', $result);
        self::assertGreaterThanOrEqual(self::TIMER_SECONDS, $serverTimeElapsed);
        self::assertLessThan(self::MAX_WALL_CLOCK_SECONDS, $wallClockElapsed);
    }

    public function testChildWorkflowSkipsItsTimerRegardlessOfEarlierTests(): void
    {
        $serverTimeBefore = $this->testingService->getCurrentTime();
        $wallClockBefore = \microtime(true);

        $child = $this->workflowClient->newWorkflowStub(LongTimerWorkflow::class);
        $run = $this->workflowClient->start($child, self::TIMER_SECONDS);
        $result = $run->getResult('string', self::MAX_WALL_CLOCK_SECONDS);

        $wallClockElapsed = \microtime(true) - $wallClockBefore;
        $serverTimeElapsed = $this->testingService->getCurrentTime()->getTimestamp() - $serverTimeBefore->getTimestamp();

        self::assertSame('done', $result);
        self::assertGreaterThanOrEqual(self::TIMER_SECONDS, $serverTimeElapsed);
        self::assertLessThan(self::MAX_WALL_CLOCK_SECONDS, $wallClockElapsed);
    }

    protected function tearDown(): void
    {
        $this->childWorkflowMocks?->clear();
        $this->childWorkflowMocks = null;

        parent::tearDown();
    }

    private function childWorkflowMocks(): WorkflowMocker
    {
        return $this->childWorkflowMocks ??= new WorkflowMocker();
    }
}
