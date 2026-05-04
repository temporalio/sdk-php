<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Errors;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationException;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Acceptance test: Nexus error handling — OperationException and HandlerException.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class ErrorsTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function operationFailureReturns424FailedDependency(
        State $state,
        #[Stub('Extra_Nexus_Errors_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $this->setupEndpoint($helper, $state->namespace);

        [$code, $resp] = $helper->postOperation($endpointId, 'ErrorService', 'failOp', 'business-error');

        // Per Nexus spec: UnsuccessfulOperationError → 424 Failed Dependency.
        // This proves OperationException does NOT collapse into HandlerError(Internal),
        // which was the P0.4 bug. The operation state (failed|canceled) is carried
        // out-of-band in the Nexus response envelope, not in the failure body.
        self::assertSame(424, $code, "OperationException must map to 424, got {$code}. Body: {$resp}");
        self::assertStringContainsString('business-error', $resp, 'Failure message should be propagated');
    }

    #[Test]
    public function handlerInternalErrorReturns500(
        State $state,
        #[Stub('Extra_Nexus_Errors_Bootstrap2')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $this->setupEndpoint($helper, $state->namespace);

        [$code, $resp] = $helper->postOperation($endpointId, 'ErrorService', 'handlerErrorOp', 'infra');

        // ErrorType::Internal → HTTP 500.
        self::assertSame(500, $code, "Internal handler error must map to 500, got {$code}. Body: {$resp}");
    }

    #[Test]
    public function handlerBadRequestReturns400(
        State $state,
        #[Stub('Extra_Nexus_Errors_Bootstrap3')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $this->setupEndpoint($helper, $state->namespace);

        [$code, $resp] = $helper->postOperation($endpointId, 'ErrorService', 'badRequestOp', 'bad-input');

        // ErrorType::BadRequest must NOT be collapsed into 500 — this is the P0.1 bug regression test.
        self::assertSame(400, $code, "BadRequest handler error must map to 400, got {$code}. Body: {$resp}");
    }

    #[Test]
    public function handlerUnauthorizedReturns403(
        State $state,
        #[Stub('Extra_Nexus_Errors_Bootstrap4')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $this->setupEndpoint($helper, $state->namespace);

        [$code, $resp] = $helper->postOperation($endpointId, 'ErrorService', 'unauthorizedOp', 'deny');

        self::assertSame(403, $code, "Unauthorized handler error must map to 403, got {$code}. Body: {$resp}");
    }

    #[Test]
    public function handlerNotFoundReturns404(
        State $state,
        #[Stub('Extra_Nexus_Errors_Bootstrap5')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $this->setupEndpoint($helper, $state->namespace);

        [$code] = $helper->postOperation($endpointId, 'ErrorService', 'notFoundOp', 'missing');

        self::assertSame(404, $code);
    }

    #[Test]
    public function unknownOperationReturns404(
        State $state,
        #[Stub('Extra_Nexus_Errors_Bootstrap6')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $this->setupEndpoint($helper, $state->namespace);

        [$code, $resp] = $helper->postOperation($endpointId, 'ErrorService', 'nonExistentOp', 'whatever');

        // ServiceHandler::newUnrecognizedOperationException uses ErrorType::NotFound.
        self::assertSame(404, $code, "Unknown op → NOT_FOUND → 404, got {$code}. Body: {$resp}");
    }

    #[Test]
    public function operationCanceledFromHandlerPropagates(
        State $state,
        #[Stub('Extra_Nexus_Errors_Bootstrap7')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $this->setupEndpoint($helper, $state->namespace);

        [$code, $resp] = $helper->postOperation($endpointId, 'ErrorService', 'cancelOp', 'bye');

        // Canceled operation is still a business-level UnsuccessfulOperationError → 424.
        // The actual state (canceled vs failed) lives in the Nexus envelope, not body.
        self::assertSame(424, $code, "Canceled → 424, got {$code}. Body: {$resp}");
        self::assertStringContainsString('bye', $resp, 'Failure message should be propagated');
    }

    /**
     * Verifies the failure cause/stack-trace forwarding (Fix 3).
     *
     * The handler throws a HandlerException whose cause is a runtime
     * exception with a known marker in its message. sdk-php's
     * NexusTaskHandler::attachTracebackAsDetails() must JSON-encode the
     * cause chain into Nexus.Failure.details, so the response body
     * surfaces both the outer exception type and the inner cause's
     * message — instead of just the outer message (the pre-Fix-3
     * behaviour).
     */
    #[Test]
    public function handlerExceptionForwardsCauseChainInResponse(
        State $state,
        #[Stub('Extra_Nexus_Errors_Bootstrap8')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $this->setupEndpoint($helper, $state->namespace);

        [$code, $resp] = $helper->postOperation(
            $endpointId,
            'ErrorService',
            'failWithCause',
            'outer-fail',
        );

        self::assertSame(500, $code, "Body: {$resp}");
        // Outer message must always appear.
        self::assertStringContainsString('outer-fail', $resp);
        // Cause-chain marker — set in the handler. If our wire dropped the
        // cause/stack-trace (the pre-Fix-3 behaviour), this string would
        // not be in the response.
        self::assertStringContainsString('CAUSE_CHAIN_MARKER', $resp);
    }

    private function setupEndpoint(NexusHelper $helper, string $namespace): string
    {
        return $helper->setupEndpoint(
            $namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Errors',
            'test-nexus-err',
        );
    }
}

#[Service(name: 'ErrorService')]
class ErrorService
{
    #[Operation]
    public function failOp(string $reason): string
    {
        throw OperationException::failed($reason ?: 'unknown');
    }

    #[Operation]
    public function handlerErrorOp(string $reason): string
    {
        throw HandlerException::create(ErrorType::Internal, "infra: {$reason}");
    }

    #[Operation]
    public function badRequestOp(string $reason): string
    {
        throw HandlerException::create(ErrorType::BadRequest, "bad: {$reason}");
    }

    #[Operation]
    public function unauthorizedOp(string $reason): string
    {
        throw HandlerException::create(ErrorType::Unauthorized, "deny: {$reason}");
    }

    #[Operation]
    public function notFoundOp(string $reason): string
    {
        throw HandlerException::create(ErrorType::NotFound, "missing: {$reason}");
    }

    #[Operation]
    public function cancelOp(string $reason): string
    {
        throw OperationException::canceled($reason ?: 'canceled');
    }

    #[Operation]
    public function failWithCause(string $reason): string
    {
        // Two-level cause chain. The marker in the inner cause's
        // message is what the acceptance test asserts to prove the
        // chain reached the caller.
        $inner = new \RuntimeException('CAUSE_CHAIN_MARKER: db unavailable');
        $middle = new \LogicException("middle of {$reason}", 0, $inner);
        throw HandlerException::create(
            ErrorType::Internal,
            $reason ?: 'unknown',
            cause: $middle,
        );
    }
}

#[WorkflowInterface]
class ErrorsBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Errors_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class ErrorsBootstrapWorkflow2
{
    #[WorkflowMethod(name: 'Extra_Nexus_Errors_Bootstrap2')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class ErrorsBootstrapWorkflow3
{
    #[WorkflowMethod(name: 'Extra_Nexus_Errors_Bootstrap3')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class ErrorsBootstrapWorkflow4
{
    #[WorkflowMethod(name: 'Extra_Nexus_Errors_Bootstrap4')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class ErrorsBootstrapWorkflow5
{
    #[WorkflowMethod(name: 'Extra_Nexus_Errors_Bootstrap5')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class ErrorsBootstrapWorkflow6
{
    #[WorkflowMethod(name: 'Extra_Nexus_Errors_Bootstrap6')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class ErrorsBootstrapWorkflow7
{
    #[WorkflowMethod(name: 'Extra_Nexus_Errors_Bootstrap7')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class ErrorsBootstrapWorkflow8
{
    #[WorkflowMethod(name: 'Extra_Nexus_Errors_Bootstrap8')]
    public function run(): string
    {
        return 'ready';
    }
}
