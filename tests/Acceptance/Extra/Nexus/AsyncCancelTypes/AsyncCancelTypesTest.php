<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\AsyncCancelTypes;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHistoryAssertions;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusWorkerOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/** Async-cancel matrix: TryCancel, WaitCompleted, Unspecified, Abandon, cancel-before-sent. */
#[Worker(options: [self::class, 'workerOptions'])]
class AsyncCancelTypesTest extends TestCase
{
    use NexusHistoryAssertions;

    public static function workerOptions(): WorkerOptions
    {
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function tryCancel(State $state, WorkflowClientInterface $client, NexusEndpoints $endpoints): void
    {
        $stub = $this->runCancelScenario($state, $client, $endpoints, 'try-cancel');
        self::assertSame('ok', $stub->getResult('string'));
    }

    #[Test]
    public function waitCompleted(State $state, WorkflowClientInterface $client, NexusEndpoints $endpoints): void
    {
        $stub = $this->runCancelScenario($state, $client, $endpoints, 'wait-completed');
        self::assertSame('cancelled:payload', $stub->getResult('string'));
    }

    #[Test]
    public function unspecifiedDefaultsToWaitCompleted(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $stub = $this->runCancelScenario($state, $client, $endpoints, 'unspecified');

        // Unspecified (value 0, dropped on the wire) must behave like WaitCompleted.
        self::assertSame(
            'cancelled:payload',
            $stub->getResult('string'),
            'Unspecified must default to WaitCompleted behaviour.',
        );
    }

    #[Test]
    public function abandonResumesCallerImmediatelyWithoutWireCancel(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $stub = $this->runCancelScenario($state, $client, $endpoints, 'abandon');

        self::assertSame(
            'ok',
            $stub->getResult('string'),
            'Abandon must resolve the caller future immediately with a CanceledFailure.',
        );

        $history = $client->getWorkflowHistory($stub->getExecution())->getHistory();
        self::assertSame(
            0,
            self::countEvents($history, EventType::EVENT_TYPE_NEXUS_OPERATION_CANCEL_REQUESTED),
            'Abandon must NOT send a RequestCancelNexusOperation to the server.',
        );
    }

    #[Test]
    public function cancelBeforeSentResumesCallerWithoutScheduling(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $stub = $this->runCancelScenario($state, $client, $endpoints, 'cancel-before-sent');

        self::assertSame(
            'ok',
            $stub->getResult('string'),
            'Cancel before the schedule command is flushed must resolve the caller with a CanceledFailure.',
        );

        $history = $client->getWorkflowHistory($stub->getExecution())->getHistory();
        self::assertSame(
            0,
            self::countEvents($history, EventType::EVENT_TYPE_NEXUS_OPERATION_SCHEDULED),
            'Nothing must be scheduled on the server when the operation is cancelled before being sent.',
        );
    }

    private function runCancelScenario(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
        string $scenario,
    ): WorkflowStubInterface {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-cancel-' . $scenario);

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncCancelTypes_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(20)),
        );

        $client->start($stub, $endpoint->name, $scenario);

        return $stub;
    }
}

// ── Service A: long-running handler that catches cancel ────────────

#[Service(name: 'CancelTypesService')]
class CancelTypesService
{
    #[AsyncOperation(output: 'string')]
    public function longRunning(string $input): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            LongRunningHandlerWorkflow::class,
            WorkflowOptions::new()->withWorkflowId(Nexus::getStartDetails()->requestId),
            $input,
        );
    }
}

#[WorkflowInterface]
class LongRunningHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncCancelTypes_LongHandler')]
    public function handle(string $input)
    {
        try {
            yield Workflow::timer(CarbonInterval::seconds(30));
            return "completed:{$input}";
        } catch (CanceledFailure) {
            return "cancelled:{$input}";
        }
    }
}

// ── Service B: Abandon scenario — handler keeps running server-side ─

#[Service(name: 'AbandonService')]
class AbandonService
{
    #[AsyncOperation(output: 'string')]
    public function run(string $input): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            AbandonHandlerWorkflow::class,
            WorkflowOptions::new()->withWorkflowId(Nexus::getStartDetails()->requestId),
            $input,
        );
    }
}

#[WorkflowInterface]
class AbandonHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncCancelTypes_AbandonHandler')]
    public function handle(string $input)
    {
        yield Workflow::timer(CarbonInterval::seconds(5));
        return "completed:{$input}";
    }
}

// ── Caller workflow ────────────────────────────────────────────────

#[WorkflowInterface]
class CancelTypesCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncCancelTypes_Caller')]
    public function run(string $endpoint, string $scenario)
    {
        [$cancelType, $serviceClass, $opName, $waitBeforeCancel] = $this->resolveScenario($scenario);

        $stub = Workflow::newNexusServiceStub(
            $serviceClass,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(15))
                ->withCancellationType($cancelType),
        );

        $promise = null;
        $scope = Workflow::async(static function () use ($stub, $opName, &$promise): void {
            $promise = $stub->{$opName}('payload');
        });

        if ($waitBeforeCancel > 0) {
            yield Workflow::timer(CarbonInterval::milliseconds($waitBeforeCancel));
        }
        $scope->cancel();

        try {
            $result = yield $promise;

            return $result;
        } catch (NexusOperationFailure $e) {
            $cause = $e->getPrevious();
            if (!$cause instanceof CanceledFailure) {
                $causeName = $cause === null ? 'null' : $cause::class;
                return "wrong-cause:{$causeName}";
            }
            return 'ok';
        }
    }

    /**
     * @return array{int, class-string, non-empty-string, int} {cancellationType, service-FQCN, op-method, ms-to-wait}
     */
    private function resolveScenario(string $scenario): array
    {
        return match ($scenario) {
            'try-cancel' => [
                NexusOperationCancellationType::TryCancel->value,
                CancelTypesService::class,
                'longRunning',
                500,
            ],
            'wait-completed' => [
                NexusOperationCancellationType::WaitCompleted->value,
                CancelTypesService::class,
                'longRunning',
                500,
            ],
            'unspecified' => [
                NexusOperationCancellationType::Unspecified->value,
                CancelTypesService::class,
                'longRunning',
                500,
            ],
            'abandon' => [
                NexusOperationCancellationType::Abandon->value,
                AbandonService::class,
                'run',
                300,
            ],
            'cancel-before-sent' => [
                NexusOperationCancellationType::TryCancel->value,
                CancelTypesService::class,
                'longRunning',
                0,
            ],
            default => throw new \InvalidArgumentException("unknown scenario: {$scenario}"),
        };
    }
}
