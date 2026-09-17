<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\CancelAfterComplete;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusWorkerOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Cancelling a Nexus operation that has ALREADY completed naturally is a
 * harmless no-op: the result is already delivered, and the late cancel neither
 * raises an error nor rewrites the observed result.
 *
 * Mirrors the Go/Java/TS "cancel after complete" coverage. The caller awaits
 * the operation result first (so the op is terminal), THEN cancels the scope.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class CancelAfterCompleteTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function cancelAfterNaturalCompletionIsNoOp(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-cancel-after-complete');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_Cancel_AfterComplete_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(60)),
        );

        $client->start($stub, $endpoint->name, 'payload');

        self::assertSame('completed:payload', $stub->getResult('string', timeout: 30));
    }
}

// ── Nexus service: handler completes almost immediately ─────────────────

#[Service(name: 'CancelAfterCompleteService')]
class CancelAfterCompleteService
{
    #[AsyncOperation(output: 'string')]
    public function quick(string $input): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            CancelAfterCompleteHandlerWorkflow::class,
            WorkflowOptions::new()->withWorkflowId(Nexus::getStartDetails()->requestId),
            $input,
        );
    }
}

#[WorkflowInterface]
class CancelAfterCompleteHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Cancel_AfterComplete_Handler')]
    public function handle(string $input)
    {
        yield Workflow::timer(CarbonInterval::milliseconds(50));
        return "completed:{$input}";
    }
}

/**
 * Awaits the operation result first, then issues a cancel on the (now resolved)
 * scope. The cancel must be swallowed silently and the result preserved.
 */
#[WorkflowInterface]
class CancelAfterCompleteCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Cancel_AfterComplete_Caller')]
    public function run(string $endpoint, string $input)
    {
        $stub = Workflow::newNexusServiceStub(
            CancelAfterCompleteService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(30))
                ->withCancellationType(NexusOperationCancellationType::WaitRequested),
        );

        $promise = null;
        $scope = Workflow::async(static function () use ($stub, $input, &$promise): void {
            $promise = $stub->quick($input);
        });

        try {
            $result = yield $promise;
        } catch (CanceledFailure) {
            return 'unexpected-cancel-before-completion';
        }

        $scope->cancel();

        yield Workflow::timer(CarbonInterval::milliseconds(50));

        return $result;
    }
}
