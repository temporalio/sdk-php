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
 * **Expected (happy path):**
 *   1. Caller workflow starts; calls `nexusStub->hello($input)`.
 *   2. PHP sends ExecuteNexusOperation; RR invokes `wp.env.ExecuteNexusOperation`.
 *   3. RR's started callback fires (token != "") → pushes
 *      `NexusStartEnvelope{async:true, token}` back to PHP.
 *   4. PHP gets the envelope, kicks the `Workflow::async` polling loop.
 *   5. Handler workflow runs (50 ms timer + return "HELLO, …!"), completes.
 *   6. Server records `NexusOperationCompleted` on the caller workflow.
 *   7. Caller gets a new task; SDK fires the completion callback →
 *      `state.done = true` in nexusOps.
 *   8. Next `GetNexusOperationResult` poll picks up the result.
 *   9. Caller workflow returns the result; client gets "HELLO, WORLD!".
 *
 * **Actual (currently broken):**
 *   Steps 1–5 happen normally. Step 6 NEVER occurs — the caller workflow only
 *   ever receives 4 tasks (start, NexusOperationStarted, polling timer scheduled,
 *   timer fired) and the workflow times out waiting for completion.
 *
 * **Hypothesis:** completion callback URL plumbing missing in the dev-server
 * setup OR `WorkflowRunOperation::start()` isn't wiring the callback (see
 * {@see \Temporal\Nexus\WorkflowRunOperation::start()} where `$details->callbackUrl`
 * is checked — likely empty here). Caller never gets `NexusOperationCompleted`
 * in its history, so the SDK's completion callback in RR never fires and
 * `state.done` stays `false` → polling returns "not ready" forever.
 *
 * The execution timeout below is intentionally tight (5 s) so this test
 * fails fast while the underlying issue is open. Handler completes in ~2.5 s,
 * so a passing run finishes in well under 5 s. Once the completion path is
 * fixed, no further changes here are needed.
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
interface AsyncWorkflowService
{
    #[AsyncOperation(output: 'string')]
    public function hello(string $input): OperationInfo;
}

class AsyncWorkflowServiceImpl implements AsyncWorkflowService
{
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
