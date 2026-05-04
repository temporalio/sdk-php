<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\AsyncCancelTypes;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Nexus\WorkflowRunOperation;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * P1 #6–8 — async-cancel matrix.
 *
 * Existing {@see \Temporal\Tests\Acceptance\Extra\Nexus\AsyncCancel\AsyncCancelByTokenTest}
 * covers `WaitRequested`. This suite covers the rest:
 *   - `TryCancel`     — caller resumes after cancel is sent; handler observes
 *                       a CanceledFailure too.
 *   - `WaitCompleted` — caller waits until the handler workflow has fully
 *                       finished after the cancel.
 *   - `Abandon`       — caller's cancel is suppressed, handler runs to natural
 *                       completion, caller observes the handler's result.
 *
 * P1 #9 ("cancel before handler started") is still deferred — `Workflow::async()
 * + immediate cancel()` raced and the caller workflow failed with
 * retryState=NON_RETRYABLE. Needs a different primitive. Tracked in nexus_plan.md.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class AsyncCancelTypesTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function tryCancel(State $state, WorkflowClientInterface $client): void
    {
        // TryCancel: caller resumes as soon as the cancel is sent. The
        // handler workflow may still be running — the caller doesn't wait.
        // Caller's catch block runs first, returns 'ok'.
        self::assertSame('ok', $this->runCancelScenario($state, $client, 'try-cancel'));
    }

    #[Test]
    public function waitCompleted(State $state, WorkflowClientInterface $client): void
    {
        // WaitCompleted: caller waits until the handler workflow has fully
        // finished after the cancel. Our handler catches CanceledFailure and
        // returns "cancelled:payload" — the caller observes that value as the
        // operation result (not a CanceledFailure).
        self::assertSame(
            'cancelled:payload',
            $this->runCancelScenario($state, $client, 'wait-completed'),
        );
    }

    #[Test]
    public function abandon(State $state, WorkflowClientInterface $client): void
    {
        // Abandon: caller's cancel is suppressed at the wire level. The handler
        // workflow never sees a CanceledFailure and runs to natural completion;
        // the caller observes the handler's normal return value.
        self::assertSame(
            'completed:payload',
            $this->runCancelScenario($state, $client, 'abandon'),
        );
    }

    private function runCancelScenario(
        State $state,
        WorkflowClientInterface $client,
        string $scenario,
    ): string {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-cancel-' . $scenario,
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncCancelTypes_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(20)),
        );

        $client->start($stub, $endpoint['name'], $scenario);

        return $stub->getResult('string');
    }
}

// ── Service A: long-running handler that catches cancel ────────────

#[Service(name: 'CancelTypesService')]
class CancelTypesService
{
    #[AsyncOperation(output: 'string')]
    public function longRunning(string $input): OperationInfo
    {
        $details = Nexus::getStartDetails();
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                LongRunningHandlerWorkflow::class,
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

// ── Service B: short handler for Abandon scenario ──────────────────
//
// Abandon suppresses the wire cancel, so the caller awaits the handler's
// natural completion. A 1s timer keeps the test fast.

#[Service(name: 'AbandonService')]
class AbandonService
{
    #[AsyncOperation(output: 'string')]
    public function run(string $input): OperationInfo
    {
        $details = Nexus::getStartDetails();
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                AbandonHandlerWorkflow::class,
                WorkflowOptions::new()->withWorkflowId($details->requestId),
                $input,
            ),
            $details,
        );
    }

    #[OperationCancel(operation: 'run')]
    public function cancel(string $token): void
    {
        WorkflowRunOperation::cancel($token);
    }
}

#[WorkflowInterface]
class AbandonHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncCancelTypes_AbandonHandler')]
    public function handle(string $input)
    {
        try {
            yield Workflow::timer(CarbonInterval::seconds(1));
            return "completed:{$input}";
        } catch (CanceledFailure) {
            // Should NOT reach here in the Abandon scenario.
            return "WRONG-cancelled:{$input}";
        }
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
            // For TryCancel: handler observes cancel and rejects via
            // CanceledFailure (caller catches it before handler completes).
            // For WaitCompleted: caller awaits the handler's CanceledFailure-
            // recovery path, which returns "cancelled:payload" naturally.
            // For Abandon: cancel is suppressed at the wire level, handler
            // runs to completion and returns its natural result.
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
            'abandon' => [
                NexusOperationCancellationType::Abandon->value,
                AbandonService::class,
                'run',
                300,
            ],
            default => throw new \InvalidArgumentException("unknown scenario: {$scenario}"),
        };
    }
}
