<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\ParallelCancel;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\History;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Nexus\Attribute\AsyncOperation;
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
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/** Scope cancel over Promise::all of N async Nexus ops fans out per-sibling. */
#[Worker(options: [self::class, 'workerOptions'])]
class CancelPropagationTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function cancellingScopeWithPromiseAllCancelsAllSiblings(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
        #[Stub('Extra_Nexus_ParallelCancel_Bootstrap')]
        WorkflowStubInterface $bootstrapStub,
    ): void {
        $bootstrapStub->getResult('string');

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-cancel-prop');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_ParallelCancel_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(60)),
        );

        $client->start($stub, $endpoint->name);

        self::assertSame('cancelled', $stub->getResult('string'));

        $history = $client->getWorkflowHistory($stub->getExecution())->getHistory();
        $scheduled = self::countEvents($history, EventType::EVENT_TYPE_NEXUS_OPERATION_SCHEDULED);
        $cancelRequested = self::countEvents($history, EventType::EVENT_TYPE_NEXUS_OPERATION_CANCEL_REQUESTED);

        self::assertSame(
            3,
            $scheduled,
            'All three siblings must reach the schedule event before cancel.',
        );
        self::assertSame(
            3,
            $cancelRequested,
            'Cancel must fan out to every sibling — proves Scope::onRequest registers per-promise onCancel.',
        );

        // CANCELED-event count is server-timing dependent (handler returns 'cancelled:tag'
        // normally, so the op may close as COMPLETED) — only fan-out is load-bearing.
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

#[Service(name: 'CancelPropagationService')]
class CancelPropagationService
{
    #[AsyncOperation(output: 'string')]
    public function longRunning(string $input): OperationInfo
    {
        $details = Nexus::getStartDetails();
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                CancelPropagationHandlerWorkflow::class,
                WorkflowOptions::new()->withWorkflowId($details->requestId),
                $input,
            ),
            $details,
        );
    }

    #[OperationCancel(operation: 'longRunning')]
    public function cancelLongRunning(string $token): void
    {
        WorkflowRunOperation::cancel($token);
    }
}

#[WorkflowInterface]
class CancelPropagationHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ParallelCancel_Handler')]
    public function handle(string $input)
    {
        try {
            yield Workflow::timer(CarbonInterval::seconds(45));
            return "completed:{$input}";
        } catch (CanceledFailure) {
            return "cancelled:{$input}";
        }
    }
}

#[WorkflowInterface]
class CancelPropagationCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ParallelCancel_Caller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            CancelPropagationService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(60))
                ->withCancellationType(NexusOperationCancellationType::WaitRequested),
        );

        $combined = null;
        $scope = Workflow::async(static function () use ($stub, &$combined): void {
            $combined = Promise::all([
                $stub->longRunning('a'),
                $stub->longRunning('b'),
                $stub->longRunning('c'),
            ]);
        });

        yield Workflow::timer(CarbonInterval::seconds(2));
        $scope->cancel();

        try {
            yield $combined;
            return 'unexpected-no-failure';
        } catch (NexusOperationFailure $e) {
            if ($e->getPrevious() instanceof CanceledFailure) {
                return 'cancelled';
            }
            return 'unexpected-cause:' . ($e->getPrevious()?->getMessage() ?? 'null');
        } catch (CanceledFailure) {
            return 'cancelled';
        }
    }
}

#[WorkflowInterface]
class ParallelCancelBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ParallelCancel_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}
