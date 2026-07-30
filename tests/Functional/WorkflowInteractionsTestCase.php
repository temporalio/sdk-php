<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\LocalActivityReturningWorkflow;
use Temporal\Tests\Workflow\SignalChildViaStubWorkflow;
use Temporal\Tests\Workflow\SimpleWorkflow;
use Temporal\Tests\Workflow\TimerWorkflow;
use Temporal\Tests\Workflow\WithChildWorkflow;

final class WorkflowInteractionsTestCase extends WorkflowTestCase
{
    public function testActivityInteractionsOfRealWorkflow(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(SimpleWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'hello');
        self::assertSame('HELLO', $run->getResult('string', 10));

        $interactions = $this->interactions($run);

        $interactions->activity('SimpleActivity.echo')->withInput('hello')->assertCalledOnce();
        $interactions->activity('SimpleActivity.echo')->withInput('nope')->assertNeverCalled();
        $interactions->activity('SimpleActivity.greet')->assertNeverCalled();
        $interactions->assertNoOtherActivities();
    }

    public function testChildWorkflowInteractionsOfRealWorkflow(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(WithChildWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'input');
        self::assertSame('Child: CHILD INPUT', $run->getResult('string', 10));

        $interactions = $this->interactions($run);

        $interactions->childWorkflow('SimpleWorkflow')->assertStartedOnce();
        $interactions->childWorkflow('Other')->assertNeverStarted();
    }

    public function testTimerInteractionsOfRealWorkflow(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(TimerWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'INPUT');
        self::assertSame('input', $run->getResult('string', 10));

        $interactions = $this->interactions($run);

        $interactions->timer()->assertStarted('1 second');
        $interactions->timer()->assertStarted(1000);
        $interactions->timer()->assertStartedTimes(1);
    }

    public function testExternalSignalInteractionsOfRealWorkflow(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(SignalChildViaStubWorkflow::class);
        $run = $this->workflowClient->start($workflow);
        self::assertSame(8, $run->getResult('int', 10));

        $interactions = $this->interactions($run);

        $interactions->childWorkflow('SimpleSignalledWorkflow')->assertStartedOnce();
        $interactions->signal('add')->assertSentOnce();
        $interactions->signal('nonexistent')->assertNeverSent();
    }

    public function testLocalActivityInteractionsOfRealWorkflow(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(LocalActivityReturningWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'input');
        $run->getResult(null, 10);

        $interactions = $this->interactions($run);

        $interactions->localActivity('JustLocalActivity.echo')->assertCalledOnce();
        $interactions->localActivity('Nope')->assertNeverCalled();
    }
}
