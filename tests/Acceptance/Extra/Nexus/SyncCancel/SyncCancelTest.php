<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\SyncCancel;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
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

/**
 * Cancelling a SYNC Nexus operation before its schedule command is flushed is
 * well-defined: the cancel runs in the same workflow task that issued the op,
 * before anything reaches the server, so the operation is cancelled and the
 * caller observes a {@see NexusOperationFailure} whose cause is a
 * {@see CanceledFailure}. This matches the sdk-go/java/ts "cancel before
 * started" contract; the workflow must not crash.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class SyncCancelTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function cancellingSyncOperationIsNoOp(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-sync-cancel');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_SyncCancel_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(30)),
        );

        $client->start($stub, $endpoint->name, 'world');

        self::assertSame('cancelled', $stub->getResult('string', timeout: 20));
    }
}

// ── Sync Nexus service ──────────────────────────────────────────────────

#[Service(name: 'SyncCancelService')]
class SyncCancelService
{
    #[Operation]
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}

// ── Caller workflow: invoke sync op inside a scope, then cancel ─────────

#[WorkflowInterface]
class SyncCancelCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_SyncCancel_Caller')]
    public function run(string $endpoint, string $name)
    {
        $stub = Workflow::newNexusServiceStub(
            SyncCancelService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(20))
                ->withCancellationType(NexusOperationCancellationType::TryCancel),
        );

        $promise = null;
        $scope = Workflow::async(static function () use ($stub, $name, &$promise): void {
            $promise = $stub->greet($name);
        });

        $scope->cancel();

        try {
            return yield $promise;
        } catch (NexusOperationFailure $e) {
            return $e->getPrevious() instanceof CanceledFailure ? 'cancelled' : 'wrong-cause';
        }
    }
}
