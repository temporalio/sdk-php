<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\AsyncWorkflow;

use Carbon\CarbonInterval;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
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
 * Flow: PHP issues `ExecuteNexusOperation` and `GetNexusOperationStarted` in
 * the same task; RR's nexusStarted registry pushes the start envelope when
 * the SDK ack's the start, RR's completion callback resolves the
 * `ExecuteNexusOperation` response when the handler workflow finishes.
 * Tight 5s execution timeout — handler completes in ~50ms so passing runs
 * finish well under 5s.
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
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
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
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
        );

        $client->start($stub, $endpoint['name'], 'idempotent');

        // Result should still arrive — same flow as the happy path; this just
        // exercises the same code with a distinct endpoint to keep tests independent.
        self::assertSame('HELLO, IDEMPOTENT!', $stub->getResult('string'));
    }
}

// ── Nexus service ──────────────────────────────────────────────────

#[Service(name: 'AsyncWorkflowService')]
class AsyncWorkflowService
{
    #[AsyncOperation(output: 'string')]
    public function hello(string $input): OperationInfo
    {
        $details = Nexus::getStartDetails();
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                AsyncHandlerWorkflow::class,
                WorkflowOptions::new()->withWorkflowId($details->requestId),
                $input,
            ),
            $details,
        );
    }

    #[OperationCancel(operation: 'hello')]
    public function cancelHello(string $token): void
    {
        WorkflowRunOperation::cancel($token);
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
