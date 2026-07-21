<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\AsyncWorkflow;

use Carbon\CarbonInterval;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Nexus\WorkflowHandle;
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

/** End-to-end async Nexus operation: caller → WorkflowRunOperation → handler workflow → completion. */
#[Worker(options: [self::class, 'workerOptions'])]
class WorkflowRunOperationTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function asyncWorkflowOperationCompletesAndPropagatesResult(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-async-wf');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncWorkflow_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(20)),
        );

        $client->start($stub, $endpoint->name, 'world');

        self::assertSame('HELLO, WORLD!', $stub->getResult('string'));
    }

    /** Proves the requestId really becomes the handler workflow id (file marker carries it across processes). */
    #[Test]
    public function requestIdIsPropagatedToHandlerWorkflowId(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        RequestIdMarker::clear();

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-async-wf-rid');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncWorkflow_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(20)),
        );

        $client->start($stub, $endpoint->name, 'idempotent');

        self::assertSame('HELLO, IDEMPOTENT!', $stub->getResult('string'));

        $requestId = RequestIdMarker::read();
        self::assertNotNull($requestId, 'Handler never recorded the Nexus start requestId.');
        self::assertNotSame('', $requestId, 'Recorded Nexus requestId must be non-empty.');

        $handlerStub = $client->newUntypedRunningWorkflowStub(
            $requestId,
            workflowType: 'Extra_Nexus_AsyncWorkflow_Handler',
        );
        self::assertSame('HELLO, IDEMPOTENT!', $handlerStub->getResult('string', timeout: 10));

        RequestIdMarker::clear();
    }
}

/** File-backed marker: handler runs in a RR worker process separate from PHPUnit. */
final class RequestIdMarker
{
    public const FILE = '/tmp/nexus-async-wf-request-id-marker-nexus-async-wf-rid';

    public static function record(string $requestId): void
    {
        \file_put_contents(self::FILE, $requestId);
    }

    public static function read(): ?string
    {
        if (!\is_file(self::FILE)) {
            return null;
        }
        $contents = \file_get_contents(self::FILE);
        return $contents === false ? null : $contents;
    }

    public static function clear(): void
    {
        if (\is_file(self::FILE)) {
            \unlink(self::FILE);
        }
    }
}

// ── Nexus service ──────────────────────────────────────────────────

#[Service(name: 'AsyncWorkflowService')]
class AsyncWorkflowService
{
    #[AsyncOperation(output: 'string')]
    public function hello(string $input): WorkflowHandle
    {
        $details = Nexus::getStartDetails();
        RequestIdMarker::record($details->requestId);
        return WorkflowHandle::fromWorkflowMethod(
            AsyncHandlerWorkflow::class,
            WorkflowOptions::new()->withWorkflowId($details->requestId),
            $input,
        );
    }
}

// ── Handler workflow ───────────────────────────────────────────────

#[WorkflowInterface]
class AsyncHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncWorkflow_Handler')]
    public function handle(string $input)
    {
        // Yield once so the operation goes async instead of collapsing into a sync result.
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
