<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\ChildWorkflow\CancelAbandon;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class CancelAbandonTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_ChildWorkflow_CancelAbandon')]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        self::markTestSkipped('To be resolved with https://github.com/temporalio/sdk-php/issues/634');

        # Find the child workflow execution ID
        $deadline = \microtime(true) + 10;
        child_id:
        $execution = null;
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            if ($event->hasChildWorkflowExecutionStartedEventAttributes()) {
                $execution = $event->getChildWorkflowExecutionStartedEventAttributes()->getWorkflowExecution();
                break;
            }
        }

        if ($execution === null && \microtime(true) < $deadline) {
            goto child_id;
        }

        self::assertNotNull($execution, 'Child workflow execution not found in history');

        # Get Child Workflow Stub
        $child = $client->newUntypedRunningWorkflowStub(
            $execution->getWorkflowId(),
            $execution->getRunId(),
            'Harness_ChildWorkflow_CancelAbandon_Child',
        );

        # Cancel the parent workflow
        $stub->cancel();
        # Expect the CanceledFailure in the parent workflow
        self::assertSame('cancelled', $stub->getResult());

        # Signal the child workflow to exit
        $child->signal('exit');
        # No canceled failure in the child workflow
        self::assertSame('test 42', $child->getResult());
    }
}

#[WorkflowInterface]
class MainWorkflow
{
    #[WorkflowMethod('Harness_ChildWorkflow_CancelAbandon')]
    public function run()
    {
        $child = Workflow::newUntypedChildWorkflowStub(
            'Harness_ChildWorkflow_CancelAbandon_Child',
            Workflow\ChildWorkflowOptions::new()
                ->withParentClosePolicy(Workflow\ParentClosePolicy::Abandon),
        );

        yield $child->start('test 42');

        try {
            return yield $child->getResult();
        } catch (CanceledFailure) {
            return 'cancelled';
        } catch (ChildWorkflowFailure $failure) {
            # Check CanceledFailure
            return $failure->getPrevious()::class === CanceledFailure::class
                ? 'cancelled'
                : throw $failure;
        }
    }
}

#[WorkflowInterface]
class ChildWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod('Harness_ChildWorkflow_CancelAbandon_Child')]
    public function run(string $input)
    {
        yield Workflow::await(fn(): bool => $this->exit);
        return $input;
    }

    #[Workflow\SignalMethod('exit')]
    public function exit(): void
    {
        $this->exit = true;
    }
}
