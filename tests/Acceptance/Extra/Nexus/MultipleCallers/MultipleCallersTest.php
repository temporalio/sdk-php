<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\MultipleCallers;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
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
 * P4 #18 — two caller workflows invoke the same async Nexus operation with
 * a shared handler workflow ID. Server-side `WorkflowIdConflictPolicy::UseExisting`
 * (hardcoded in {@see WorkflowRunOperation::start()}) causes the second start
 * to attach to the existing handler workflow instead of failing or starting a
 * new run.
 *
 * Both callers must observe the same single result from the shared handler.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class MultipleCallersTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function twoCallersShareOneAsyncHandlerWorkflow(
        State $state,
        WorkflowClientInterface $client,
    ): void {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-shared-async',
        );

        $callerA = $client->newUntypedWorkflowStub(
            'Extra_Nexus_MultipleCallers_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(20)),
        );

        $callerB = $client->newUntypedWorkflowStub(
            'Extra_Nexus_MultipleCallers_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(20)),
        );

        // Start both callers; they target the same shared-handler workflow ID.
        $client->start($callerA, $endpoint['name']);
        // Tiny stagger so the second caller is more likely to hit the already-
        // started handler rather than racing on creation. The conflict policy
        // (UseExisting) makes either order safe; the stagger just exercises the
        // "join existing" path more reliably.
        \usleep(200_000);
        $client->start($callerB, $endpoint['name']);

        $resultA = $callerA->getResult('string');
        $resultB = $callerB->getResult('string');

        // Handler returns "shared-handler-result"; both callers see the same value.
        self::assertSame('shared-handler-result', $resultA);
        self::assertSame('shared-handler-result', $resultB);
    }
}

#[Service(name: 'SharedAsyncService')]
class SharedAsyncService
{
    #[AsyncOperation(output: 'string')]
    public function run(string $input): OperationInfo
    {
        // Fixed handler workflow ID so both callers join the same execution.
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                SharedHandlerWorkflow::class,
                WorkflowOptions::new()->withWorkflowId(SharedHandlerWorkflow::ID),
                $input,
            ),
            Nexus::getStartDetails(),
        );
    }

    #[OperationCancel(operation: 'run')]
    public function cancel(string $token): void
    {
        WorkflowRunOperation::cancel($token);
    }
}

#[WorkflowInterface]
class SharedHandlerWorkflow
{
    public const ID = 'extra-nexus-multiplecallers-shared-handler';

    #[WorkflowMethod(name: 'Extra_Nexus_MultipleCallers_SharedHandler')]
    public function handle(string $input)
    {
        // Brief work; result is fixed so both callers verify the same value.
        yield Workflow::timer(CarbonInterval::milliseconds(200));
        return 'shared-handler-result';
    }
}

#[WorkflowInterface]
class MultipleCallersCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_MultipleCallers_Caller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            SharedAsyncService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(15)),
        );

        return yield $stub->run('payload');
    }
}
