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
    public function resetWorker(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        # Create a Workflow stub with an execution timeout 12 seconds
        $stub = $client->withTimeout(1)
            ->newUntypedWorkflowStub(
                'Extra_Stability_ResetWorker',
                WorkflowOptions::new()
                    ->withTaskQueue($feature->taskQueue)
                    ->withWorkflowExecutionTimeout(12),
            );

        # Start the Workflow with a 10-second timer
        $client->start($stub, 10);

        # Query the Workflow to kill the Worker
        try {
            $stub->query('die');
            self::fail('Query must fail with a timeout');
        } catch (WorkflowServiceException $e) {
            # Should fail with a timeout
            self::assertInstanceOf(TimeoutException::class, $e->getPrevious());
        }

        # Cancel Workflow
        $stub->cancel();

        try {
            # Workflow must be canceled
            $stub->getResult(timeout: 12);
        } catch (WorkflowFailedException $e) {
            self::assertInstanceOf(CanceledFailure::class, $e->getPrevious());
            return;
        }

        self::fail('Workflow must fail with a canceled failure');
    }
}

#[Workflow\WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod('Extra_Stability_ResetWorker')]
    #[ReturnType(Type::TYPE_STRING)]
    public function expire(int $seconds = 10): \Generator
    {
        yield Workflow::timer($seconds);

        return yield 'Timer';
    }

    #[Workflow\QueryMethod('die')]
    public function die(int $sleep = 2): void
    {
        \sleep($sleep);
        exit(1);
    }
}
