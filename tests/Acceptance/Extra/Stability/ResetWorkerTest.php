<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Stability\ResetWorker;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\Type;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowMethod;

class ResetWorkerTest extends TestCase
{
    #[Test]
    public function resetWithCancel(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        # Create a Workflow stub with an execution timeout 20 seconds
        $stub = $client->withTimeout(1)
            ->newUntypedWorkflowStub(
                'Extra_Stability_ResetWorker',
                WorkflowOptions::new()
                    ->withTaskQueue($feature->taskQueue)
                    ->withWorkflowExecutionTimeout(20),
            );

        # Start the Workflow with a 10-second timer
        $client->start($stub, 10);

        # Query the Workflow to kill the Worker
        try {
            /** @see TestWorkflow::die() */
            $stub->query('die', 2);
            self::fail('Query must fail with a timeout');
        } catch (WorkflowServiceException $e) {
            # Should fail with a timeout
            $e->getPrevious() instanceof CanceledFailure or tr(clientException: $e);
        }

        # Cancel Workflow
        $stub->cancel();

        try {
            # Workflow must be canceled
            $stub->getResult(timeout: 20);
        } catch (WorkflowFailedException $e) {
            $e->getPrevious() instanceof CanceledFailure or tr(clientException: $e);
            return;
        }

        self::fail('Workflow must fail with a canceled failure');
    }

    #[Test]
    public function resetWithSignal(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        # Create a Workflow stub with an execution timeout 20 seconds
        $stub = $client->withTimeout(1)
            ->newUntypedWorkflowStub(
                'Extra_Stability_ResetWorker',
                WorkflowOptions::new()
                    ->withTaskQueue($feature->taskQueue)
                    ->withWorkflowExecutionTimeout(20),
            );

        # Start the Workflow with a 10-second timer
        $client->start($stub, 10);

        # Query the Workflow to kill the Worker
        try {
            /** @see TestWorkflow::die() */
            $stub->query('die', 2);
            self::fail('Query must fail with a timeout');
        } catch (WorkflowServiceException $e) {
            # Should fail with a timeout
            $e->getPrevious() instanceof TimeoutException or tr(clientException: $e);
        }

        $stub->signal('exit');

        try {
            # Workflow must be canceled
            $result = $stub->getResult(timeout: 8);
            self::assertSame('Signal', $result);
        } catch (\Throwable) {
            $this->fail('Workflow must finish successfully and no timeout must be thrown');
        }

        # Check that Side Effect was not lost
        $found = false;
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            if ($event->hasMarkerRecordedEventAttributes()) {
                $record = $event->getMarkerRecordedEventAttributes();
                self::assertSame('SideEffect', $record->getMarkerName());
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'Side Effect must be found in the Workflow history');
    }
}

#[Workflow\WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod('Extra_Stability_ResetWorker')]
    #[ReturnType(Type::TYPE_STRING)]
    public function expire(int $seconds = 10): \Generator
    {
        $isTimer = ! yield Workflow::awaitWithTimeout($seconds, fn(): bool => $this->exit);

        return yield $isTimer ? 'Timer' : 'Signal';
    }

    #[Workflow\QueryMethod('die')]
    public function die(int $sleep = 2): void
    {
        \sleep($sleep);
        exit(1);
    }

    #[Workflow\SignalMethod('exit')]
    public function signal()
    {
        yield Workflow::uuid7();
        $this->exit = true;
    }
}
