<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\MultipleCallers;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\WorkflowIdConflictPolicy;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusWorkerOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[Worker(options: [self::class, 'workerOptions'])]
class MultipleCallersTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function twoCallersShareOneAsyncHandlerWorkflow(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-shared-async');

        $handlerWorkflowId = 'shared-handler-' . \bin2hex(\random_bytes(8));

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

        $client->start($callerA, $endpoint->name, $handlerWorkflowId);
        $client->start($callerB, $endpoint->name, $handlerWorkflowId);

        $handlerStub = $client->newUntypedRunningWorkflowStub($handlerWorkflowId);
        $signaled = false;
        $deadline = \microtime(true) + 5.0;
        do {
            try {
                $handlerStub->signal('unblock');
                $signaled = true;
                break;
            } catch (WorkflowNotFoundException) {
                \usleep(50_000);
            }
        } while (\microtime(true) < $deadline);

        if (!$signaled) {
            self::fail('handler never became signalable within 5s');
        }

        self::assertSame('shared-handler-result', $callerA->getResult('string'));
        self::assertSame('shared-handler-result', $callerB->getResult('string'));
    }
}

#[Service(name: 'SharedAsyncService')]
class SharedAsyncService
{
    #[AsyncOperation(output: 'string')]
    public function run(string $handlerWorkflowId): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            SharedHandlerWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($handlerWorkflowId)
                ->withWorkflowIdConflictPolicy(WorkflowIdConflictPolicy::UseExisting),
            $handlerWorkflowId,
        );
    }
}

#[WorkflowInterface]
class SharedHandlerWorkflow
{
    private bool $unblocked = false;

    #[WorkflowMethod(name: 'Extra_Nexus_MultipleCallers_SharedHandler')]
    public function handle(string $input)
    {
        yield Workflow::await(fn() => $this->unblocked);
        return 'shared-handler-result';
    }

    #[SignalMethod(name: 'unblock')]
    public function unblock(): void
    {
        $this->unblocked = true;
    }
}

#[WorkflowInterface]
class MultipleCallersCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_MultipleCallers_Caller')]
    public function run(string $endpoint, string $handlerWorkflowId)
    {
        $stub = Workflow::newNexusServiceStub(
            SharedAsyncService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(15)),
        );

        return yield $stub->run($handlerWorkflowId);
    }
}
