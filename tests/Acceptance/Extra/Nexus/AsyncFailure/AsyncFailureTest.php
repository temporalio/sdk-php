<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\AsyncFailure;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Exception\Failure\TerminatedFailure;
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
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * P0 #4–5 — caller-workflow failure mapping for ASYNC Nexus operations
 * (workflow-run handlers).
 *
 * Two scenarios:
 *   4. handler workflow throws {@see ApplicationFailure} → caller receives
 *      {@see NexusOperationFailure} with the failure message preserved.
 *   5. handler workflow is terminated externally → caller receives
 *      {@see NexusOperationFailure} (cause is either {@see TerminatedFailure}
 *      or some other failure whose message contains "terminat").
 */
#[Worker(options: [self::class, 'workerOptions'])]
class AsyncFailureTest extends TestCase
{
    use NexusHistoryAssertions;

    public static function workerOptions(): WorkerOptions
    {
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function callerReceivesFailureWhenHandlerWorkflowThrows(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-async-handler-fail');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncFailure_HandlerFailsCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
        );

        $client->start($stub, $endpoint->name);

        self::assertSame('ok', $stub->getResult('string'));
    }

    #[Test]
    public function callerReceivesFailureWhenHandlerWorkflowTerminated(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-async-handler-term');

        $callerStub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncFailure_TerminateCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
        );

        $client->start($callerStub, $endpoint->name);

        if (!self::historyContains($client, $callerStub, EventType::EVENT_TYPE_NEXUS_OPERATION_STARTED, 5.0)) {
            self::fail('Handler workflow never reached NEXUS_OPERATION_STARTED within 5s; nothing to terminate.');
        }

        $handlerStub = $client->newUntypedRunningWorkflowStub(HandlerWorkflowToTerminate::ID);
        $handlerStub->terminate('test-terminate');

        self::assertSame('ok', $callerStub->getResult('string'));
    }
}

// ── Service A: handler workflow throws ApplicationFailure ──────────

#[Service(name: 'AsyncFailingService')]
class AsyncFailingService
{
    #[AsyncOperation(output: 'string')]
    public function run(string $input): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            FailingHandlerWorkflow::class,
            WorkflowOptions::new()->withWorkflowId(Nexus::getStartDetails()->requestId),
            $input,
        );
    }
}

#[WorkflowInterface]
class FailingHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncFailure_FailingHandler')]
    public function handle(string $input)
    {
        // Yield once so the workflow takes a real task before failing.
        yield Workflow::timer(CarbonInterval::milliseconds(50));
        throw new ApplicationFailure(
            'handler-workflow-failed',
            'BusinessError',
            true,
        );
    }
}

#[WorkflowInterface]
class HandlerFailsCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncFailure_HandlerFailsCaller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            AsyncFailingService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(10)),
        );

        try {
            yield $stub->run('payload');
        } catch (NexusOperationFailure $e) {
            $cause = $e->getPrevious();
            if ($cause === null) {
                return 'no-cause';
            }

            $haystack = $cause->getMessage();
            if ($cause instanceof ApplicationFailure) {
                $haystack .= '|' . $cause->getOriginalMessage();
            }

            if (!\str_contains($haystack, 'handler-workflow-failed')) {
                return 'missing-message:' . $cause::class . ':' . $haystack;
            }

            return 'ok';
        }

        return 'unexpected:no-exception';
    }
}

// ── Service B: handler workflow with fixed ID, terminated externally ──

#[Service(name: 'AsyncTerminateService')]
class AsyncTerminateService
{
    #[AsyncOperation(output: 'string')]
    public function run(string $input): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            HandlerWorkflowToTerminate::class,
            // Fixed workflow ID so the test can locate and terminate it.
            WorkflowOptions::new()->withWorkflowId(HandlerWorkflowToTerminate::ID),
            $input,
        );
    }
}

#[WorkflowInterface]
class HandlerWorkflowToTerminate
{
    public const ID = 'extra-nexus-asyncfailure-handler-to-terminate';

    #[WorkflowMethod(name: 'Extra_Nexus_AsyncFailure_HandlerToTerminate')]
    public function handle(string $input)
    {
        yield Workflow::timer(CarbonInterval::seconds(30));
        return "should-never-reach:{$input}";
    }
}

#[WorkflowInterface]
class TerminateCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncFailure_TerminateCaller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            AsyncTerminateService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(12)),
        );

        try {
            yield $stub->run('payload');
        } catch (NexusOperationFailure $e) {
            $cause = $e->getPrevious();
            if ($cause === null) {
                return 'no-cause';
            }

            if ($cause instanceof TerminatedFailure) {
                return 'ok';
            }

            $haystack = \strtolower($cause->getMessage());
            if (\str_contains($haystack, 'terminat')) {
                return 'ok';
            }

            return 'unexpected-cause:' . $cause::class . ':' . $cause->getMessage();
        }

        return 'unexpected:no-exception';
    }
}
