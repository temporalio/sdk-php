<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\LocalActivityReturningWorkflow;
use Temporal\Tests\Workflow\RepeatedActivityWorkflow;
use Temporal\Tests\Workflow\SimpleWorkflow;

final class ActivityMockerTestCase extends WorkflowTestCase
{
    public function testMockedFailurePreservesTypeAndNonRetryableFlag(): void
    {
        $this->activityMocks->expectFailure(
            'SimpleActivity.echo',
            new ApplicationFailure('boom', 'MyType', nonRetryable: true),
        );

        $workflow = $this->workflowClient->newWorkflowStub(SimpleWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'input');

        try {
            $run->getResult('string', 10);
            self::fail('Expected the mocked activity failure to propagate');
        } catch (WorkflowFailedException $e) {
            $activityFailure = $e->getPrevious();
            self::assertInstanceOf(ActivityFailure::class, $activityFailure);

            $cause = $activityFailure->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $cause);
            self::assertSame('MyType', $cause->getType());
            self::assertTrue($cause->isNonRetryable());
            self::assertStringContainsString('boom', $cause->getOriginalMessage());
        }
    }

    public function testLocalActivityIsMocked(): void
    {
        $this->activityMocks->expectCompletion('JustLocalActivity.echo', 'mocked-local');

        $workflow = $this->workflowClient->newWorkflowStub(LocalActivityReturningWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'input');

        self::assertSame('mocked-local', $run->getResult('string', 10));
    }

    public function testConsecutiveCompletionsReturnPerCallInOrder(): void
    {
        $this->activityMocks->expectConsecutiveCompletions('SimpleActivity.echo', ['first', 'second']);

        $workflow = $this->workflowClient->newWorkflowStub(RepeatedActivityWorkflow::class);
        $run = $this->workflowClient->start($workflow);

        self::assertSame(['first', 'second'], $run->getResult('array', 10));
    }

    public function testCompletionMatchedByInput(): void
    {
        $this->activityMocks->expectCompletionWhen('SimpleActivity.echo', ['hello'], 'matched-hello');
        $this->activityMocks->expectCompletionWhen('SimpleActivity.echo', ['other'], 'matched-other');

        $workflow = $this->workflowClient->newWorkflowStub(SimpleWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'hello');

        self::assertSame('matched-hello', $run->getResult('string', 10));
    }
}
