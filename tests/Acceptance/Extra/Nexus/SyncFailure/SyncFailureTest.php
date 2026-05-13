<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\SyncFailure;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\NexusHandlerFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Exception\RetryBehavior;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/** Caller-workflow failure mapping for SYNC Nexus operations. */
#[Worker(options: [self::class, 'workerOptions'])]
class SyncFailureTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function callerCatchesNexusOperationFailureWithApplicationFailureCause(
        State $state,
        WorkflowClientInterface $client,
    ): void {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-sync-fail-app',
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_SyncFailure_AppFailureCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
        );

        $client->start($stub, $endpoint['name']);

        self::assertSame('ok', $stub->getResult('string'));
    }

    #[Test]
    public function handlerErrorBadRequest(State $state, WorkflowClientInterface $client): void
    {
        self::assertSame('ok', $this->runHandlerErrorScenario($state, $client, 'badRequest', 'BAD_REQUEST'));
    }

    #[Test]
    public function handlerErrorInternal(State $state, WorkflowClientInterface $client): void
    {
        self::assertSame('ok', $this->runHandlerErrorScenario($state, $client, 'internal', 'INTERNAL'));
    }

    #[Test]
    public function handlerErrorNotFound(State $state, WorkflowClientInterface $client): void
    {
        self::assertSame('ok', $this->runHandlerErrorScenario($state, $client, 'notFound', 'NOT_FOUND'));
    }

    #[Test]
    public function handlerErrorUnauthorized(State $state, WorkflowClientInterface $client): void
    {
        self::assertSame('ok', $this->runHandlerErrorScenario($state, $client, 'unauthorized', 'UNAUTHORIZED'));
    }

    #[Test]
    public function applicationFailureCausePreservesTypeMessageAndDetails(
        State $state,
        WorkflowClientInterface $client,
    ): void {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-sync-fail-rich-cause',
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_SyncFailure_RichCauseCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
        );

        $client->start($stub, $endpoint['name']);

        self::assertSame('ok', $stub->getResult('string'));
    }

    #[Test]
    public function callerReceivesNotFoundForUnknownOperation(
        State $state,
        WorkflowClientInterface $client,
    ): void {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-sync-unknown-op',
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_SyncFailure_UnknownOperationCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
        );

        $client->start($stub, $endpoint['name']);

        self::assertSame('ok', $stub->getResult('string'));
    }

    private function runHandlerErrorScenario(
        State $state,
        WorkflowClientInterface $client,
        string $opName,
        string $expectedType,
    ): string {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-sync-handler-err-' . \strtolower($opName),
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_SyncFailure_HandlerErrorCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
        );

        $client->start($stub, $endpoint['name'], $opName, $expectedType);

        return $stub->getResult('string');
    }
}

// ── Service A: throws OperationException::failed() ─────────────────

#[Service(name: 'SyncFailureAppService')]
class SyncFailureAppService
{
    #[Operation]
    public function failAlways(string $input): string
    {
        throw OperationException::failed('business-error');
    }
}

#[WorkflowInterface]
class AppFailureCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_SyncFailure_AppFailureCaller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            SyncFailureAppService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(10)),
        );

        try {
            yield $stub->failAlways('ignored');
        } catch (NexusOperationFailure $e) {
            $cause = $e->getPrevious();
            if (!$cause instanceof ApplicationFailure) {
                $causeName = $cause === null ? 'null' : $cause::class;
                return "wrong-cause-type:{$causeName}";
            }

            $haystack = $cause->getOriginalMessage() . '|' . $cause->getMessage();
            if (!\str_contains($haystack, 'business-error')) {
                return "missing-message:{$haystack}";
            }

            $typeHaystack = $cause->getType() . '|' . $cause->getOriginalMessage();
            if (!\str_contains($typeHaystack, 'nexus.OperationError.failed')) {
                return "missing-type-marker:type={$cause->getType()}:msg={$cause->getOriginalMessage()}";
            }

            return 'ok';
        }

        return 'unexpected:no-exception';
    }
}

// ── Service B: throws HandlerException with various ErrorTypes ─────

// Interface + impl shape kept here on purpose: pairs with the untyped-stub
// caller workflow below to cover the "service contract on an interface, called
// via newUntypedNexusOperationStub" path end-to-end.
#[Service(name: 'SyncHandlerErrorService')]
interface SyncHandlerErrorService
{
    #[Operation]
    public function badRequest(string $input): string;

    #[Operation]
    public function internal(string $input): string;

    #[Operation]
    public function notFound(string $input): string;

    #[Operation]
    public function unauthorized(string $input): string;
}

final class SyncHandlerErrorServiceImpl implements SyncHandlerErrorService
{
    public function badRequest(string $input): string
    {
        throw HandlerException::create(ErrorType::BadRequest, 'bad-request-msg');
    }

    public function internal(string $input): string
    {
        // `Internal` is retryable by default — force NonRetryable so the caller
        // sees the handler error directly instead of a TimeoutFailure after
        // retries exhaust the schedule-to-close window.
        throw HandlerException::create(ErrorType::Internal, 'internal-msg', null, RetryBehavior::NonRetryable);
    }

    public function notFound(string $input): string
    {
        throw HandlerException::create(ErrorType::NotFound, 'not-found-msg');
    }

    public function unauthorized(string $input): string
    {
        throw HandlerException::create(ErrorType::Unauthorized, 'unauthorized-msg');
    }
}

#[WorkflowInterface]
class HandlerErrorCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_SyncFailure_HandlerErrorCaller')]
    public function run(string $endpoint, string $opName, string $expectedErrorType)
    {
        $stub = Workflow::newUntypedNexusOperationStub(
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withService('SyncHandlerErrorService')
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(10)),
        );

        try {
            yield $stub->execute($opName, ['ignored']);
        } catch (NexusOperationFailure $e) {
            $cause = $e->getPrevious();
            if (!$cause instanceof NexusHandlerFailure) {
                $causeName = $cause === null ? 'null' : $cause::class;
                return "wrong-cause-type:{$causeName}";
            }

            if ($cause->getType() !== $expectedErrorType) {
                return "wrong-error-type:got={$cause->getType()}:want={$expectedErrorType}";
            }

            return 'ok';
        }

        return 'unexpected:no-exception';
    }
}

// ── Service D: throws OperationException::failed with a rich ApplicationFailure cause ──

#[Service(name: 'SyncFailureRichCauseService')]
class SyncFailureRichCauseService
{
    #[Operation]
    public function failWithRichCause(string $input): string
    {
        throw OperationException::failed(
            'outer-business-error',
            new ApplicationFailure(
                'inner-business-message',
                'CustomBusinessType',
                false,
                EncodedValues::fromValues(['detail-payload-marker']),
            ),
        );
    }
}

#[WorkflowInterface]
class RichCauseCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_SyncFailure_RichCauseCaller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            SyncFailureRichCauseService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(10)),
        );

        try {
            yield $stub->failWithRichCause('ignored');
        } catch (NexusOperationFailure $e) {
            $innerType = self::findApplicationFailureType($e, 'CustomBusinessType');
            if ($innerType === null) {
                return self::dumpChain($e, 'inner-CustomBusinessType-not-found-in-chain');
            }

            $messageHaystack = $innerType->getOriginalMessage() . '|' . $innerType->getMessage();
            if (!\str_contains($messageHaystack, 'inner-business-message')) {
                return "missing-inner-message:haystack={$messageHaystack}";
            }

            $details = $innerType->getDetails();
            if ($details->count() === 0) {
                return 'missing-details:zero-values';
            }

            $detailValue = $details->getValue(0, 'string');
            if ($detailValue !== 'detail-payload-marker') {
                return "wrong-detail-payload:{$detailValue}";
            }

            return 'ok';
        }

        return 'unexpected:no-exception';
    }

    private static function findApplicationFailureType(\Throwable $e, string $type): ?ApplicationFailure
    {
        $current = $e;
        while ($current !== null) {
            if ($current instanceof ApplicationFailure && $current->getType() === $type) {
                return $current;
            }
            $current = $current->getPrevious();
        }
        return null;
    }

    private static function dumpChain(\Throwable $e, string $reason): string
    {
        $entries = [];
        $current = $e;
        while ($current !== null) {
            $entry = $current::class;
            if ($current instanceof ApplicationFailure) {
                $entry .= '(type=' . $current->getType()
                    . ',msg=' . $current->getOriginalMessage()
                    . ',details=' . $current->getDetails()->count() . ')';
            } else {
                $entry .= '(msg=' . $current->getMessage() . ')';
            }
            $entries[] = $entry;
            $current = $current->getPrevious();
        }
        return $reason . '|chain=' . \implode('->', $entries);
    }
}

// ── Service C: known service for "unknown operation" scenario ──────

#[Service(name: 'KnownService')]
class KnownService
{
    #[Operation]
    public function knownOp(string $input): string
    {
        return "known:{$input}";
    }
}

#[WorkflowInterface]
class UnknownOperationCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_SyncFailure_UnknownOperationCaller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newUntypedNexusOperationStub(
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withService('KnownService')
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(10)),
        );

        try {
            yield $stub->execute('definitelyNotRegistered', ['x']);
        } catch (NexusOperationFailure $e) {
            $cause = $e->getPrevious();
            if (!$cause instanceof NexusHandlerFailure) {
                $causeName = $cause === null ? 'null' : $cause::class;
                return "wrong-cause-type:{$causeName}";
            }

            if ($cause->getType() !== 'NOT_FOUND') {
                return "wrong-error-type:{$cause->getType()}";
            }

            return 'ok';
        }

        return 'unexpected:no-exception';
    }
}
