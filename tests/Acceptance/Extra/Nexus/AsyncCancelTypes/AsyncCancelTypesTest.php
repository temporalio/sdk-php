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
 * P1 #6–7 — async-cancel matrix (subset that's well-defined on PHP today).
 *
 * The existing {@see \Temporal\Tests\Acceptance\Extra\Nexus\AsyncCancel\AsyncCancelByTokenTest}
 * covers only `WaitRequested`. This suite covers two more:
 *   - `TryCancel`     — caller resumes after cancel is sent; handler observes
 *                       a CanceledFailure too.
 *   - `WaitCompleted` — caller waits until the handler workflow has fully
 *                       finished after the cancel.
 *
 * Two scenarios that the original plan listed are deferred:
 *
 *   - `Abandon` (P1 #8) — the spec calls for cancel to NOT be propagated to
 *     the handler workflow. In PHP today, `Workflow::async()->cancel()` still
 *     reaches the handler regardless of `cancellationType`. Either the PHP
 *     caller-side API needs to honor Abandon, or this scenario needs a
 *     different test vector. Tracked as a gap in nexus_plan.md.
 *
 *   - "Cancel before handler started" (P1 #9) — `Workflow::async() + immediate
 *     cancel()` raced and the caller workflow failed with
 *     retryState=NON_RETRYABLE. Needs a different primitive (cancel between
 *     `start()` schedule and the workflow task that issues the start command).
 *     Tracked as a gap in nexus_plan.md.
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
interface CancelTypesService
{
    #[AsyncOperation(output: 'string')]
    public function longRunning(string $input): OperationInfo;
}

class CancelTypesServiceImpl implements CancelTypesService
{
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
            // For TryCancel/WaitCompleted: handler observes cancel and rejects
            // with CanceledFailure. For Abandon: cancel never reaches handler,
            // so the operation resolves with the handler's natural result.
            $result = yield $promise;

            // Expected only for Abandon — returned to the test verbatim.
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
            default => throw new \InvalidArgumentException("unknown scenario: {$scenario}"),
        };
    }
}
