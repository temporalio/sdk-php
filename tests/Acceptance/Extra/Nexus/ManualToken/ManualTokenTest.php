<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\ManualToken;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHistoryAssertions;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusWorkerOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationHandle;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Manual-token async operations: the factory method returns an
 * {@see OperationHandlerInterface} implementation owning start and cancel;
 * cancel routes to the handler's cancel() or fails with NOT_IMPLEMENTED
 * when the handler refuses cancellation.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class ManualTokenTest extends TestCase
{
    use NexusHistoryAssertions;

    public static function workerOptions(): WorkerOptions
    {
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function manualTokenReachesCallerUntouched(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-manual-token');

        $caller = $client->newUntypedWorkflowStub(
            'Extra_Nexus_ManualToken_TokenCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(30)),
        );

        $client->start($caller, $endpoint->name, 'job-1');

        self::assertSame('external-job-1', $caller->getResult('string', timeout: 30));
    }

    #[Test]
    public function cancelRoutesToOperationCancelRoutine(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-manual-cancel-ok');

        $caller = $client->newUntypedWorkflowStub(
            'Extra_Nexus_ManualToken_CancelCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(30)),
        );

        $client->start($caller, $endpoint->name, 'startCancellable', 'job-2');

        self::assertTrue(
            self::historyContains($client, $caller, EventType::EVENT_TYPE_NEXUS_OPERATION_CANCEL_REQUEST_COMPLETED),
            'Expected NEXUS_OPERATION_CANCEL_REQUEST_COMPLETED in caller history. Seen events: '
            . \implode(', ', self::historyEventNames($client, $caller)),
        );

        $caller->signal('finish');
        self::assertSame('done', $caller->getResult('string', timeout: 40));
    }

    #[Test]
    public function cancelWithoutRoutineFailsWithNotImplemented(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-manual-cancel-ni');

        $caller = $client->newUntypedWorkflowStub(
            'Extra_Nexus_ManualToken_CancelCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(30)),
        );

        $client->start($caller, $endpoint->name, 'startUncancellable', 'job-3');

        self::assertTrue(
            self::historyContains($client, $caller, EventType::EVENT_TYPE_NEXUS_OPERATION_CANCEL_REQUEST_FAILED),
            'Expected NEXUS_OPERATION_CANCEL_REQUEST_FAILED in caller history. Seen events: '
            . \implode(', ', self::historyEventNames($client, $caller)),
        );

        $caller->signal('finish');
        self::assertSame('done', $caller->getResult('string', timeout: 40));
    }
}

// ── Nexus service: manual tokens, no backing workflow ───────────────────

#[Service(name: 'ManualTokenAcceptanceService')]
class ManualTokenAcceptanceService
{
    #[AsyncOperation(output: 'string', input: 'string')]
    public function startCancellable(): CancellableExternalHandler
    {
        return new CancellableExternalHandler();
    }

    #[AsyncOperation(output: 'string', input: 'string')]
    public function startUncancellable(): UncancellableExternalHandler
    {
        return new UncancellableExternalHandler();
    }
}

final class CancellableExternalHandler implements OperationHandlerInterface
{
    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult {
        return OperationStartResult::async(new OperationInfo("external-{$param}", OperationState::Running));
    }

    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {}
}

final class UncancellableExternalHandler implements OperationHandlerInterface
{
    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult {
        return OperationStartResult::async(new OperationInfo("external-{$param}", OperationState::Running));
    }

    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {
        throw HandlerException::create(ErrorType::NotImplemented, 'manual operation does not support cancellation');
    }
}

// ── Caller: starts the op, returns the operation token, abandons the op ──

#[WorkflowInterface]
class TokenCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ManualToken_TokenCaller')]
    public function run(string $endpoint, string $input)
    {
        $stub = Workflow::newUntypedNexusOperationStub(
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withService('ManualTokenAcceptanceService')
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(20))
                ->withCancellationType(NexusOperationCancellationType::Abandon),
        );

        /** @var NexusOperationHandle<string> $handle */
        $handle = yield $stub->start('startCancellable', [$input], 'string');

        return $handle->getOperationToken();
    }
}

// ── Caller: starts the named op, cancels it, then waits for the `finish` signal ──

#[WorkflowInterface]
class CancelCallerWorkflow
{
    private bool $finished = false;

    #[WorkflowMethod(name: 'Extra_Nexus_ManualToken_CancelCaller')]
    public function run(string $endpoint, string $operation, string $input)
    {
        $stub = Workflow::newUntypedNexusOperationStub(
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withService('ManualTokenAcceptanceService')
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(20))
                ->withCancellationType(NexusOperationCancellationType::TryCancel),
        );

        $handle = null;
        $scope = Workflow::async(static function () use ($stub, $operation, $input, &$handle) {
            $handle = yield $stub->start($operation, [$input], 'string');
            yield $handle->getResult();
        });

        yield Workflow::await(static function () use (&$handle): bool {
            return $handle !== null;
        });
        yield Workflow::timer(CarbonInterval::seconds(NexusWorkerOptions::PRE_CANCEL_TIMER_SECONDS));
        $scope->cancel();

        try {
            yield $scope;
        } catch (\Throwable) {
        }

        yield Workflow::await(fn(): bool => $this->finished);

        return 'done';
    }

    #[SignalMethod]
    public function finish(): void
    {
        $this->finished = true;
    }
}
