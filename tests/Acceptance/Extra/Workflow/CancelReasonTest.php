<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\CancelReason;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class CancelReasonTest extends TestCase
{
    #[Test]
    public function clientCancelReasonReachesWorkflow(
        #[Stub('Extra_Workflow_CancelReasonTarget')] WorkflowStubInterface $stub,
    ): void {
        $stub->cancel('operator asked');

        $this->assertSame('operator asked', $stub->getResult(timeout: 10));
    }

    #[Test]
    public function clientCancelWithoutReasonGivesEmptyMessage(
        #[Stub('Extra_Workflow_CancelReasonTarget')] WorkflowStubInterface $stub,
    ): void {
        $stub->cancel();

        $this->assertSame('', $stub->getResult(timeout: 10));
    }

    #[Test]
    public function externalCancelReasonReachesTargetWorkflow(
        #[Stub('Extra_Workflow_CancelReasonTarget')] WorkflowStubInterface $target,
        WorkflowClientInterface $client,
    ): void {
        $canceller = $client->newUntypedWorkflowStub(
            'Extra_Workflow_CancelReasonCanceller',
            WorkflowOptions::new()->withTaskQueue($target->getOptions()->taskQueue),
        );
        $client->start($canceller, $target->getExecution()->getID(), 'from canceller');
        $canceller->getResult(timeout: 10);

        $this->assertSame('from canceller', $target->getResult(timeout: 10));
    }
}

#[WorkflowInterface]
class TargetWorkflow
{
    #[WorkflowMethod(name: 'Extra_Workflow_CancelReasonTarget')]
    public function handle()
    {
        try {
            yield Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure $e) {
            return $e->getOriginalMessage();
        }
    }
}

#[WorkflowInterface]
class CancellerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Workflow_CancelReasonCanceller')]
    public function handle(string $workflowId, string $reason)
    {
        yield Workflow::newUntypedExternalWorkflowStub(new WorkflowExecution($workflowId))->cancel($reason);
    }
}
