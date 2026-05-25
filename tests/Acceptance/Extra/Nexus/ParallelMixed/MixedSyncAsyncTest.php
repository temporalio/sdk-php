<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\ParallelMixed;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\History;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Nexus\WorkflowRunOperation;
use Temporal\Promise;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/** Mixed sync + async Nexus operations awaited via Promise::all in one workflow tick. */
#[Worker(options: [self::class, 'workerOptions'])]
class MixedSyncAsyncTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function syncAndAsyncOpsResolveTogetherInPromiseAll(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
        #[Stub('Extra_Nexus_ParallelMixed_Bootstrap')]
        WorkflowStubInterface $bootstrapStub,
    ): void {
        $bootstrapStub->getResult('string');

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-mixed');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_ParallelMixed_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(45)),
        );

        $client->start($stub, $endpoint->name);

        self::assertSame('sync=ok-x|async=done-y', $stub->getResult('string'));

        $history = $client->getWorkflowHistory($stub->getExecution())->getHistory();
        $scheduled = self::countEvents($history, EventType::EVENT_TYPE_NEXUS_OPERATION_SCHEDULED);
        $started = self::countEvents($history, EventType::EVENT_TYPE_NEXUS_OPERATION_STARTED);
        $completed = self::countEvents($history, EventType::EVENT_TYPE_NEXUS_OPERATION_COMPLETED);

        self::assertSame(
            2,
            $scheduled,
            'Both sync and async siblings must emit NEXUS_OPERATION_SCHEDULED.',
        );
        self::assertSame(
            1,
            $started,
            'Only the async sibling emits NEXUS_OPERATION_STARTED — discriminator vs sync path.',
        );
        self::assertSame(
            2,
            $completed,
            'Both siblings must terminate via NEXUS_OPERATION_COMPLETED.',
        );
    }

    private static function countEvents(History $history, int $type): int
    {
        $count = 0;
        foreach ($history->getEvents() as $event) {
            if ($event->getEventType() === $type) {
                $count++;
            }
        }
        return $count;
    }
}

#[Service(name: 'MixedSyncAsyncService')]
class MixedSyncAsyncService
{
    #[Operation]
    public function syncOp(string $tag): string
    {
        return 'ok-' . $tag;
    }

    #[AsyncOperation(output: 'string')]
    public function asyncOp(string $tag): OperationInfo
    {
        $details = Nexus::getStartDetails();
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                MixedAsyncHandlerWorkflow::class,
                WorkflowOptions::new()->withWorkflowId($details->requestId),
                $tag,
            ),
            $details,
        );
    }

    #[OperationCancel(operation: 'asyncOp')]
    public function cancelAsyncOp(string $token): void
    {
        WorkflowRunOperation::cancel($token);
    }
}

#[WorkflowInterface]
class MixedAsyncHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ParallelMixed_AsyncHandler')]
    public function handle(string $tag)
    {
        // Force a workflow-task transition so the operation actually goes async
        // (without a yield it collapses to the sync-async path, skipping NEXUS_OPERATION_STARTED).
        yield Workflow::timer(CarbonInterval::milliseconds(100));
        return 'done-' . $tag;
    }
}

#[WorkflowInterface]
class MixedSyncAsyncCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ParallelMixed_Caller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            MixedSyncAsyncService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(30)),
        );

        [$syncResult, $asyncResult] = yield Promise::all([
            $stub->syncOp('x'),
            $stub->asyncOp('y'),
        ]);

        return "sync={$syncResult}|async={$asyncResult}";
    }
}

#[WorkflowInterface]
class ParallelMixedBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ParallelMixed_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}
