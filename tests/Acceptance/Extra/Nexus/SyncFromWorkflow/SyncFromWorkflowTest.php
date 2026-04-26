<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\SyncFromWorkflow;

use Carbon\CarbonInterval;
use Nexus\Sdk\Attribute\Operation;
use Nexus\Sdk\Attribute\OperationImpl;
use Nexus\Sdk\Attribute\Service;
use Nexus\Sdk\Attribute\ServiceImpl;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationHandlerInterface;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Handler\SynchronousOperationHandler;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
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
    ): void {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-sync-from-wf',
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_SyncFromWorkflow_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(30)),
        );

        $client->start($stub, $endpoint['name'], 'world');

        self::assertSame('Hello, world!', $stub->getResult('string'));
    }
}

#[Service(name: 'SyncFromWorkflowService')]
interface SyncFromWorkflowService
{
    #[Operation]
    public function greet(string $name): string;
}

#[ServiceImpl(service: SyncFromWorkflowService::class)]
class SyncFromWorkflowServiceImpl
{
    #[OperationImpl]
    public function greet(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $ctx, OperationStartDetails $d, ?string $name): string
                => "Hello, {$name}!",
        );
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
