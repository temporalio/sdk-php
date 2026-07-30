<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\SimpleWorkflow;

final class WorkflowTestCaseInteractionsTestCase extends WorkflowTestCase
{
    public function testMockedActivityAndInteractionsFromBase(): void
    {
        $this->activityMocks->expectCompletion('SimpleActivity.echo', 'mocked');

        $workflow = $this->workflowClient->newWorkflowStub(SimpleWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'hello');

        self::assertSame('mocked', $run->getResult('string', 10));

        $this->interactions($run)
            ->activity('SimpleActivity.echo')
            ->withInput('hello')
            ->assertCalledOnce();
    }
}
