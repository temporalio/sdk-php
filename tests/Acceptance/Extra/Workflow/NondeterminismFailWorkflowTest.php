<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\NondeterminismFailWorkflow;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\Enums\V1\TaskQueueType;
use Temporal\Api\Taskqueue\V1\PollerInfo;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Api\Workflowservice\V1\DescribeTaskQueueRequest;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Worker\WorkerOptions;
use Temporal\Worker\WorkflowPanicPolicy;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Worker(options: [WorkerFactory::class, 'options'])]
class NondeterminismFailWorkflowTest extends TestCase
{
    private const WAIT_TIMEOUT_SECONDS = 15;
    private const POLL_MICROSECONDS = 100_000;

    #[Test]
    public function nondeterminismAfterReplayFailsTheWorkflow(
        WorkflowClientInterface $client,
        Feature $feature,
        State $runtime,
    ): void {
        $marker = \sys_get_temp_dir() . '/temporal-nondeterminism-' . \bin2hex(\random_bytes(8));
        $stub = $client->withTimeout(1)->newUntypedWorkflowStub(
            'Extra_Workflow_NondeterminismFailWorkflow',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withWorkflowExecutionTimeout(30),
        );
        $client->start($stub, $marker);
        $this->waitUntil(
            fn(): bool => $this->firstWorkflowTaskCompleted($client, $stub),
            'The first workflow task did not complete',
        );
        $pollers = $this->pollerIdentities($client, $runtime->namespace, $feature->taskQueue);

        try {
            $stub->query('die');
        } catch (WorkflowServiceException) {
        }
        $this->waitUntil(
            fn(): bool => \array_diff($this->pollerIdentities($client, $runtime->namespace, $feature->taskQueue), $pollers) !== [],
            'No replacement worker polls the task queue',
        );
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

    private function waitUntil(\Closure $condition, string $failure): void
    {
        $deadline = \microtime(true) + self::WAIT_TIMEOUT_SECONDS;
        while (\microtime(true) < $deadline) {
            if ($condition()) {
                return;
            }
            \usleep(self::POLL_MICROSECONDS);
        }
        self::fail($failure);
    }

    private function firstWorkflowTaskCompleted(WorkflowClientInterface $client, WorkflowStubInterface $stub): bool
    {
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            if ($event->getEventType() === EventType::EVENT_TYPE_WORKFLOW_TASK_COMPLETED) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<string>
     */
    private function pollerIdentities(WorkflowClientInterface $client, string $namespace, string $taskQueue): array
    {
        $response = $client->getServiceClient()->DescribeTaskQueue(
            (new DescribeTaskQueueRequest())
                ->setNamespace($namespace)
                ->setTaskQueue((new TaskQueue())->setName($taskQueue))
                ->setTaskQueueType(TaskQueueType::TASK_QUEUE_TYPE_WORKFLOW),
        );

        return \array_map(
            static fn(PollerInfo $poller): string => $poller->getIdentity(),
            \iterator_to_array($response->getPollers()),
        );
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
