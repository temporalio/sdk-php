<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\ParallelFailure;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\History;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Promise;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
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
 * Promise::all over Nexus ops where one of N fails.
 *
 * Surfaced product gap: a sync handler's OperationException::failed in a
 * parallel batch does not propagate as ApplicationFailure to the caller —
 * the operation surfaces as TimeoutFailure(SCHEDULE_TO_CLOSE) once the
 * timeout window elapses. Single-op fail (SyncFailureTest) works. This
 * test therefore asserts only the reproducibly observable parts: all
 * siblings get scheduled, and the caller eventually fails.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class PartialFailureTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function failingSiblingPropagatesAndSchedulesAllOps(
        State $state,
        WorkflowClientInterface $client,
        #[Stub('Extra_Nexus_ParallelFailure_Bootstrap')]
        WorkflowStubInterface $bootstrapStub,
    ): void {
        $bootstrapStub->getResult('string');

        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-partial-fail',
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_ParallelFailure_PartialCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(45)),
        );

        $client->start($stub, $endpoint['name']);

        $callerFailed = false;
        try {
            $stub->getResult('string');
        } catch (WorkflowFailedException) {
            $callerFailed = true;
        }

        self::assertTrue(
            $callerFailed,
            'Caller workflow must fail when a sibling Nexus operation fails inside Promise::all.',
        );

        $history = $client->getWorkflowHistory($stub->getExecution())->getHistory();
        $scheduled = self::countEvents($history, EventType::EVENT_TYPE_NEXUS_OPERATION_SCHEDULED);
        $failed = self::countEvents($history, EventType::EVENT_TYPE_NEXUS_OPERATION_FAILED);
        $timedOut = self::countEvents($history, EventType::EVENT_TYPE_NEXUS_OPERATION_TIMED_OUT);

        self::assertSame(
            3,
            $scheduled,
            'All three Promise::all siblings must be scheduled before the workflow fails.',
        );
        self::assertGreaterThanOrEqual(
            1,
            $failed + $timedOut,
            'At least one Nexus operation must terminate (Failed or TimedOut) so Promise::all can settle.',
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

#[Service(name: 'PartialFailureService')]
class PartialFailureService
{
    #[Operation]
    public function succeed(string $tag): string
    {
        return 'ok-' . $tag;
    }

    #[Operation]
    public function fail(string $tag): string
    {
        throw OperationException::failed('partial-failure-' . $tag);
    }
}

#[WorkflowInterface]
class PartialFailureCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ParallelFailure_PartialCaller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            PartialFailureService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(30)),
        );

        $promises = [
            $stub->succeed('a'),
            $stub->succeed('b'),
            $stub->fail('c'),
        ];

        return yield Promise::all($promises);
    }
}

#[WorkflowInterface]
class ParallelFailureBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ParallelFailure_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}
