<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Parallel;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Promise;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusWorkerOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * P4 #17 — workflow fans out N Nexus sync operations via {@see Promise::all()}
 * and waits for all of them to complete.
 *
 * Distinct from {@see \Temporal\Tests\Acceptance\Extra\Nexus\MultiOperation\MultiOperationTest}
 * which exercises multiple operations *sequentially over HTTP*. Here all the
 * operations are scheduled concurrently and awaited together — same code path
 * Java's `ParallelWorkflowOperationTest` covers.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class ParallelTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function workflowAwaitsMultipleParallelNexusOperations(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-parallel');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_Parallel_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(20)),
        );

        $client->start($stub, $endpoint->name);

        // double(1..5) → 2+4+6+8+10 = 30.
        self::assertSame('sum=30', $stub->getResult('string'));
    }
}

#[Service(name: 'ParallelService')]
class ParallelService
{
    #[Operation]
    public function double(int $input): int
    {
        return $input * 2;
    }
}

#[WorkflowInterface]
class ParallelCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Parallel_Caller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            ParallelService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(15)),
        );

        $promises = [];
        for ($i = 1; $i <= 5; $i++) {
            $promises[] = $stub->double($i);
        }

        $results = yield Promise::all($promises);

        return 'sum=' . \array_sum($results);
    }
}
