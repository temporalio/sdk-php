<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\NondeterminismFailWorkflow;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Worker\WorkerOptions;
use Temporal\Worker\WorkflowPanicPolicy;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Worker(options: [WorkerFactory::class, 'options'])]
class NondeterminismFailWorkflowTest extends TestCase
{
    private const WORKER_EXIT_WAIT_MICROSECONDS = 2_500_000;
    private const FIRST_TASK_TIMEOUT_SECONDS = 10;
    private const HISTORY_POLL_MICROSECONDS = 100_000;

    #[Test]
    public function nondeterminismAfterReplayFailsTheWorkflow(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $marker = \sys_get_temp_dir() . '/temporal-nondeterminism-' . \bin2hex(\random_bytes(8));
        $stub = $client->withTimeout(1)->newUntypedWorkflowStub(
            'Extra_Workflow_NondeterminismFailWorkflow',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withWorkflowExecutionTimeout(30),
        );
        $client->start($stub, $marker);
        $this->waitForFirstWorkflowTask($client, $stub);

        try {
            $stub->query('die');
        } catch (WorkflowServiceException) {
        }
        \usleep(self::WORKER_EXIT_WAIT_MICROSECONDS);
        $stub->signal('go');

        try {
            $stub->getResult(timeout: 20);
            self::fail('The workflow must fail on nondeterminism');
        } catch (WorkflowFailedException) {
            self::assertTrue(true);
        } finally {
            @\unlink($marker);
        }
    }

    private function waitForFirstWorkflowTask(WorkflowClientInterface $client, WorkflowStubInterface $stub): void
    {
        $deadline = \microtime(true) + self::FIRST_TASK_TIMEOUT_SECONDS;
        while (\microtime(true) < $deadline) {
            foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
                if ($event->getEventType() === EventType::EVENT_TYPE_WORKFLOW_TASK_COMPLETED) {
                    return;
                }
            }
            \usleep(self::HISTORY_POLL_MICROSECONDS);
        }
        self::fail('The first workflow task did not complete');
    }
}

class WorkerFactory
{
    public static function options(): WorkerOptions
    {
        return WorkerOptions::new()->withWorkflowPanicPolicy(WorkflowPanicPolicy::FailWorkflow);
    }
}

#[Workflow\WorkflowInterface]
class TestWorkflow
{
    private bool $go = false;

    #[WorkflowMethod('Extra_Workflow_NondeterminismFailWorkflow')]
    public function run(string $marker): \Generator
    {
        if (\file_exists($marker)) {
            yield Workflow::timer(1);
        } else {
            \touch($marker);
            yield Workflow::sideEffect(static fn(): int => 1);
        }

        yield Workflow::await(fn(): bool => $this->go);

        return 'done';
    }

    #[Workflow\QueryMethod('die')]
    public function die(): void
    {
        \sleep(2);
        exit(1);
    }

    #[Workflow\SignalMethod('go')]
    public function go(): void
    {
        $this->go = true;
    }
}
