<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Update\Fibers\TimeoutTest;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowUpdateRPCTimeoutOrCanceledException;
use Temporal\Tests\Acceptance\App\Attribute\Client;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\UpdateMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class TimeoutTest extends TestCase
{
    #[Test]
    public function getUpdateResultFromHandler(
        #[Stub('Extra_Timeout_Fibers_WorkflowUpdate')]
        WorkflowStubInterface $stub,
    ): void {
        /** @see TestWorkflow::sleep */
        $handle = $stub->startUpdate('sleep', '1 second');

        $this->expectException(WorkflowUpdateRPCTimeoutOrCanceledException::class);

        $handle->getResult(0.2);
    }

    #[Test]
    public function doUpdateWithTimeout(
        #[Stub('Extra_Timeout_Fibers_WorkflowUpdate')]
        #[Client(timeout: 1.2)]
        WorkflowStubInterface $stub,
    ): void {
        $this->expectException(WorkflowUpdateRPCTimeoutOrCanceledException::class);

        /** @see TestWorkflow::sleep */
        $stub->update('sleep', '2 second');
    }

    #[Test]
    public function withoutRunningWorker(WorkflowClientInterface $client): void
    {
        $client = $client->withTimeout(1.2);
        $wf = $client->newUntypedWorkflowStub('Extra_Timeout_Fibers_WorkflowUpdate', WorkflowOptions::new()
            ->withTaskQueue('not-existing-task-queue'));
        $client->start($wf);

        $this->expectException(WorkflowUpdateRPCTimeoutOrCanceledException::class);

        /** @see TestWorkflow::sleep */
        $wf->update('sleep', '2 second');
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Timeout_Fibers_WorkflowUpdate")]
    public function handle()
    {
        Workflow::await(static fn() => false);
    }

    #[UpdateMethod(name: 'sleep')]
    public function sleep(string $sleep): void
    {
        Workflow::timer(\DateInterval::createFromDateString($sleep));
    }
}
