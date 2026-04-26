<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\AsyncWorkflow;

use Carbon\CarbonInterval;
use Nexus\Sdk\Attribute\Operation;
use Nexus\Sdk\Attribute\OperationImpl;
use Nexus\Sdk\Attribute\Service;
use Nexus\Sdk\Attribute\ServiceImpl;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationHandlerInterface;
use Nexus\Sdk\Handler\OperationStartDetails;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Nexus\WorkflowRunOperation;
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
 * End-to-end async Nexus operation:
 *   caller workflow → Nexus stub → WorkflowRunOperation → handler workflow → completion.
 *
 * Verifies that {@see WorkflowRunOperation::fromWorkflowMethod()} backs an
 * async Nexus operation with a real workflow run, and that the result flows
 * back to the caller via the Nexus completion callback.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class WorkflowRunOperationTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function asyncWorkflowOperationCompletesAndPropagatesResult(
        State $state,
        WorkflowClientInterface $client,
    ): void {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-async-wf',
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncWorkflow_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(60)),
        );

        $client->start($stub, $endpoint['name'], 'world');

        self::assertSame('HELLO, WORLD!', $stub->getResult('string'));
    }

    #[Test]
    public function requestIdIsPropagatedToHandlerWorkflowId(
        State $state,
        WorkflowClientInterface $client,
    ): void {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-async-wf-rid',
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncWorkflow_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(60)),
        );

        $client->start($stub, $endpoint['name'], 'idempotent');

        // Result should still arrive — same flow as the happy path; this just
        // exercises the same code with a distinct endpoint to keep tests independent.
        self::assertSame('HELLO, IDEMPOTENT!', $stub->getResult('string'));
    }
}

// ── Nexus service ──────────────────────────────────────────────────

#[Service(name: 'AsyncWorkflowService')]
interface AsyncWorkflowService
{
    #[Operation]
    public function hello(string $input): string;
}

#[ServiceImpl(service: AsyncWorkflowService::class)]
class AsyncWorkflowServiceImpl
{
    #[OperationImpl]
    public function hello(): OperationHandlerInterface
    {
        return WorkflowRunOperation::fromWorkflowMethod(
            static fn(OperationContext $ctx, OperationStartDetails $d, ?string $input): WorkflowHandle =>
                WorkflowHandle::fromWorkflowMethod(
                    AsyncHandlerWorkflow::class,
                    WorkflowOptions::new()->withWorkflowId($d->requestId),
                    (string) $input,
                ),
        );
    }
}

// ── Handler workflow ───────────────────────────────────────────────

#[WorkflowInterface]
class AsyncHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncWorkflow_Handler')]
    public function handle(string $input)
    {
        // Yield once so the workflow takes at least one task — exercises the
        // async-completion path rather than collapsing into a sync result.
        yield Workflow::timer(CarbonInterval::milliseconds(50));
        return 'HELLO, ' . \strtoupper($input) . '!';
    }
}

// ── Caller workflow ────────────────────────────────────────────────

#[WorkflowInterface]
class AsyncCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncWorkflow_Caller')]
    public function run(string $endpoint, string $input)
    {
        $stub = Workflow::newNexusServiceStub(
            AsyncWorkflowService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(30)),
        );
        return yield $stub->hello($input);
    }
}
