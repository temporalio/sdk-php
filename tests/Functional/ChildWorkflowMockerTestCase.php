<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Api\Enums\V1\EventType;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Testing\WorkflowMocker;
use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\ParentWithStubbableChildWorkflow;
use Temporal\Workflow\WorkflowExecution;

final class ChildWorkflowMockerTestCase extends WorkflowTestCase
{
    private ?WorkflowMocker $childWorkflowMocks = null;

    public function testControlRealChildRunsWhenNoStubConfigured(): void
    {
        [$result, $execution] = $this->runParent('StubbableChildWorkflow', 'hello');

        self::assertSame(['ok', 'real child: hello'], $result);
        $this->assertContainsEvent($execution, EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED);
    }

    public function testControlRealChildFailureIsObservedByParent(): void
    {
        [$result, $execution] = $this->runParent('FailingChildWorkflow', 'boom');

        self::assertSame('error', $result[0]);
        self::assertStringStartsWith(ChildWorkflowFailure::class . ':', $result[1]);
        self::assertStringContainsString('real child exploded: boom', \implode("\n", $result));
        $this->assertContainsEvent($execution, EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED);
    }

    public function testStubbedChildReturnsConfiguredValueToParent(): void
    {
        $this->childWorkflowMocks()->expectCompletion('StubbableChildWorkflow', 'stubbed child value');

        [$result] = $this->runParent('StubbableChildWorkflow', 'hello');

        self::assertSame(['ok', 'stubbed child value'], $result);
    }

    public function testStubbedChildFailureIsObservedByParent(): void
    {
        $this->childWorkflowMocks()->expectFailure(
            'StubbableChildWorkflow',
            new \RuntimeException('stubbed child exploded'),
        );

        [$result] = $this->runParent('StubbableChildWorkflow', 'hello');

        self::assertSame('error', $result[0]);
        self::assertStringStartsWith(ChildWorkflowFailure::class . ':', $result[1]);
        self::assertStringContainsString('stubbed child exploded', \implode("\n", $result));
    }

    public function testStubbedChildFailureWithMultiArgExceptionPreservesCause(): void
    {
        $this->childWorkflowMocks()->expectFailure(
            'StubbableChildWorkflow',
            new ApplicationFailure('multi-arg boom', 'MyType', nonRetryable: true),
        );

        [$result] = $this->runParent('StubbableChildWorkflow', 'hello');

        self::assertSame('error', $result[0]);
        self::assertStringStartsWith(ChildWorkflowFailure::class . ':', $result[1]);
        $chain = \implode("\n", $result);
        self::assertStringContainsString(ApplicationFailure::class, $chain);
        self::assertStringContainsString('MyType', $chain);
        self::assertStringContainsString('multi-arg boom', $chain);
    }

    public function testStubbedChildDoesNotStartRealChildExecution(): void
    {
        $this->childWorkflowMocks()->expectCompletion('StubbableChildWorkflow', 'stubbed child value');

        [$result, $execution] = $this->runParent('StubbableChildWorkflow', 'hello');

        self::assertSame(['ok', 'stubbed child value'], $result);
        $this->assertNotContainsEvent($execution, EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED);
        $this->assertNotContainsEvent($execution, EventType::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_STARTED);
        $this->assertNotContainsEvent($execution, EventType::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_COMPLETED);
    }

    public function testStubIsVisibleInTheTestThatConfiguresIt(): void
    {
        $this->childWorkflowMocks()->expectCompletion('StubbableChildWorkflow', 'value from the leaking test');

        [$result] = $this->runParent('StubbableChildWorkflow', 'hello');

        self::assertSame(['ok', 'value from the leaking test'], $result);
    }

    public function testStubFromPreviousTestDoesNotLeakIntoThisOne(): void
    {
        [$result, $execution] = $this->runParent('StubbableChildWorkflow', 'hello');

        self::assertSame(['ok', 'real child: hello'], $result);
        $this->assertContainsEvent($execution, EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED);
    }

    public function testMockedChildIsInvisibleToWorkflowInteractions(): void
    {
        $this->childWorkflowMocks()->expectCompletion('StubbableChildWorkflow', 'stubbed');

        $parent = $this->workflowClient->newWorkflowStub(ParentWithStubbableChildWorkflow::class);
        $run = $this->workflowClient->start($parent, 'StubbableChildWorkflow', 'hello');

        self::assertSame(['ok', 'stubbed'], $run->getResult('array', 30));

        $this->interactions($run)->childWorkflow('StubbableChildWorkflow')->assertNeverStarted();
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

    /**
     * @return array{0: array, 1: WorkflowExecution}
     */
    private function runParent(string $childType, string $input): array
    {
        $parent = $this->workflowClient->newWorkflowStub(ParentWithStubbableChildWorkflow::class);
        $run = $this->workflowClient->start($parent, $childType, $input);

        return [$run->getResult('array', 30), $run->getExecution()];
    }

    private function assertContainsEvent(WorkflowExecution $execution, int $event): void
    {
        if (!$this->hasEvent($execution, $event)) {
            self::fail(\sprintf('Event %s not found in workflow history', EventType::value($event)));
        }

        self::assertTrue(true);
    }

    private function assertNotContainsEvent(WorkflowExecution $execution, int $event): void
    {
        if ($this->hasEvent($execution, $event)) {
            self::fail(\sprintf('Event %s unexpectedly found in workflow history', EventType::value($event)));
        }

        self::assertTrue(true);
    }

    private function hasEvent(WorkflowExecution $execution, int $event): bool
    {
        foreach ($this->workflowClient->getWorkflowHistory($execution, pageSize: 100) as $item) {
            if ($item->getEventType() === $event) {
                return true;
            }
        }

        return false;
    }
}
