<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\SyncFromWorkflow;

use Carbon\CarbonInterval;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Smoke test for the workflow → Nexus path with a SYNC handler.
 *
 * Distinct from the Cancel/Async suite (which only exercises HTTP-side
 * Nexus invocation): here a workflow yields a Nexus stub call and waits
 * for the result over the SDK wire.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class SyncFromWorkflowTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function workflowCallsSyncNexusOperationAndGetsResult(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-sync-from-wf');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_SyncFromWorkflow_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(30)),
        );

        $client->start($stub, $endpoint->name, 'world');

        self::assertSame('Hello, world!', $stub->getResult('string'));
    }
}

// This test deliberately keeps the interface + impl shape so we cover the
// "service contract on an interface, impl on a separate class" path end-to-end
// — the rest of the Nexus acceptance suite runs the class-only shape.
#[Service(name: 'SyncFromWorkflowService')]
interface SyncFromWorkflowService
{
    #[Operation]
    public function greet(string $name): string;
}

final class SyncFromWorkflowServiceImpl implements SyncFromWorkflowService
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}

#[WorkflowInterface]
class SyncFromWorkflowCaller
{
    #[WorkflowMethod(name: 'Extra_Nexus_SyncFromWorkflow_Caller')]
    public function run(string $endpoint, string $name)
    {
        $stub = Workflow::newNexusServiceStub(
            SyncFromWorkflowService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(20)),
        );
        return yield $stub->greet($name);
    }
}
