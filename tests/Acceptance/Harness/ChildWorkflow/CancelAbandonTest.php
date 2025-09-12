<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\ChildWorkflow\CancelAbandon;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Promise;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class CancelAbandonTest extends TestCase
{
    /**
     * If an abandoned Child Workflow is started in the main Workflow scope,
     * the Child Workflow should not be affected by the cancellation of the parent workflow.
     * But need to consider that we can miss the Cancellation signal if awaiting only on the Child Workflow.
     * In the {@see MainScopeWorkflow} we use Timer + Child Workflow to ensure we catch the Cancellation signal.
     */
    #[Test]
    public static function childWorkflowInMainScope(
        #[Stub('Harness_ChildWorkflow_CancelAbandon_MainScope', args: ['test 42'])]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        self::runTestScenario($stub, $client, 'test 42');
    }

    /**
     * If an abandoned Child Workflow is started in an async Scope {@see Workflow::async()} that is later cancelled,
     * the Child Workflow should not be affected by the cancellation of the parent workflow.
     * Int his case the Scope will throw the CanceledFailure.
     * @see InnerScopeCancelWorkflow
     */
    #[Test]
    public static function childWorkflowInInnerScopeCancel(
        #[Stub('Harness_ChildWorkflow_CancelAbandon_InnerScopeCancel', args: ['baz'])]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        self::runTestScenario($stub, $client, 'baz');
    }

    /**
     * If an abandoned Child Workflow is started in an async scope {@see Workflow::async()} that
     * is later cancelled manually by a Signal to the parent workflow {@see InnerScopeCancelWorkflow::close()},
     * the Child Workflow should not be affected by the cancellation of the parent scope.
     */
    #[Test]
    public static function childWorkflowInClosingInnerScope(
        #[Stub('Harness_ChildWorkflow_CancelAbandon_InnerScopeCancel', args: ['foo bar'])]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        # Get Child Workflow Stub
        $child = self::getChildWorkflowStub($client, $stub);

        # Cancel the async scope
        /** @see InnerScopeCancelWorkflow::close() */
        $stub->signal('close');
        # Expect the CanceledFailure in the parent workflow
        self::assertSame('cancelled', $stub->getResult(timeout: 5));

        # Signal the child workflow to exit
        $child->signal('exit');
        # No canceled failure in the child workflow
        self::assertSame('foo bar', $child->getResult());
    }

    /**
     * Send cancel to the parent workflow and expect the child workflow to be abandoned
     * and not cancelled.
     */
    private static function runTestScenario(
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
        string $result,
    ): void {
        # Get Child Workflow Stub
        $child = self::getChildWorkflowStub($client, $stub);

        # Cancel the parent workflow
        $stub->cancel();
        # Expect the CanceledFailure in the parent workflow
        self::assertSame('cancelled', $stub->getResult(timeout: 5));

        # Signal the child workflow to exit
        $child->signal('exit');
        # No canceled failure in the child workflow
        self::assertSame($result, $child->getResult());
    }

    /**
     * Get Child Workflow Stub
     */
    private static function getChildWorkflowStub(
        WorkflowClientInterface $client,
        WorkflowStubInterface $stub,
    ): WorkflowStubInterface {
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

        self::assertNotNull($execution, 'Child Workflow execution not found in the history.');

        # Get Child Workflow Stub
        return $client->newUntypedRunningWorkflowStub(
            $execution->getWorkflowId(),
            $execution->getRunId(),
            'Harness_ChildWorkflow_CancelAbandon_Child',
        );
    }
}

#[WorkflowInterface]
class MainScopeWorkflow
{
    #[WorkflowMethod('Harness_ChildWorkflow_CancelAbandon_MainScope')]
    public function run(string $input)
    {
        /** @see ChildWorkflow */
        $stub = Workflow::newUntypedChildWorkflowStub(
            'Harness_ChildWorkflow_CancelAbandon_Child',
            Workflow\ChildWorkflowOptions::new()
                ->withWorkflowRunTimeout('20 seconds')
                ->withParentClosePolicy(Workflow\ParentClosePolicy::Abandon),
        );

        yield $stub->start($input);

        try {
            yield Promise::race([$stub->getResult(), Workflow::timer(5)]);
            return 'timer';
        } catch (CanceledFailure) {
            return 'cancelled';
        } catch (ChildWorkflowFailure $failure) {
            # Check CanceledFailure
            return $failure->getPrevious()::class === CanceledFailure::class
                ? 'cancelled'
                : throw $failure;
        } finally {
            yield Workflow::asyncDetached(function () {
                # We shouldn't complete the Workflow immediately:
                # all the commands from the tick must be sent for testing purposes.
                yield Workflow::timer(1);
            });
        }
    }
}

#[WorkflowInterface]
class InnerScopeCancelWorkflow
{
    private CancellationScopeInterface $scope;

    #[WorkflowMethod('Harness_ChildWorkflow_CancelAbandon_InnerScopeCancel')]
    public function run(string $input)
    {
        $this->scope = Workflow::async(static function () use ($input) {
            /** @see ChildWorkflow */
            $stub = Workflow::newUntypedChildWorkflowStub(
                'Harness_ChildWorkflow_CancelAbandon_Child',
                Workflow\ChildWorkflowOptions::new()
                    ->withWorkflowRunTimeout('20 seconds')
                    ->withParentClosePolicy(Workflow\ParentClosePolicy::Abandon),
            );
            yield $stub->start($input);

            return yield $stub->getResult('string');
        });


        try {
            yield Promise::race([Workflow::timer(5) ,$this->scope]);
            return 'timer';
        } catch (CanceledFailure) {
            return 'cancelled';
        } catch (ChildWorkflowFailure $failure) {
            # Check CanceledFailure
            return $failure->getPrevious()::class === CanceledFailure::class
                ? 'cancelled'
                : throw $failure;
        } finally {
            yield Workflow::asyncDetached(function () {
                # We shouldn't complete the Workflow immediately:
                # all the commands from the tick must be sent for testing purposes.
                yield Workflow::timer(1);
            });
        }
    }

    #[Workflow\SignalMethod('close')]
    public function close(): void
    {
        $this->scope->cancel();
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
