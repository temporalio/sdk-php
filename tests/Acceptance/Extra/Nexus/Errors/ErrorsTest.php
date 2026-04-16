<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Errors;

use Nexus\Sdk\Attribute\Operation;
use Nexus\Sdk\Attribute\OperationImpl;
use Nexus\Sdk\Attribute\Service;
use Nexus\Sdk\Attribute\ServiceImpl;
use Nexus\Sdk\Exception\ErrorType;
use Nexus\Sdk\Exception\HandlerException;
use Nexus\Sdk\Exception\OperationException;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationHandlerInterface;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Handler\SynchronousOperationHandler;
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
interface ErrorServiceInterface
{
    #[Operation]
    public function failOp(string $reason): string;

    #[Operation]
    public function handlerErrorOp(string $reason): string;

    #[Operation]
    public function badRequestOp(string $reason): string;

    #[Operation]
    public function unauthorizedOp(string $reason): string;

    #[Operation]
    public function notFoundOp(string $reason): string;

    #[Operation]
    public function cancelOp(string $reason): string;
}

#[ServiceImpl(service: ErrorServiceInterface::class)]
class ErrorServiceImpl
{
    #[OperationImpl]
    public function failOp(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static function (OperationContext $ctx, OperationStartDetails $details, ?string $reason): string {
                throw OperationException::failed($reason ?? 'unknown');
            },
        );
    }

    #[OperationImpl]
    public function handlerErrorOp(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static function (OperationContext $ctx, OperationStartDetails $details, ?string $reason): string {
                throw HandlerException::create(ErrorType::Internal, "infra: {$reason}");
            },
        );
    }

    #[OperationImpl]
    public function badRequestOp(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static function (OperationContext $ctx, OperationStartDetails $details, ?string $reason): string {
                throw HandlerException::create(ErrorType::BadRequest, "bad: {$reason}");
            },
        );
    }

    #[OperationImpl]
    public function unauthorizedOp(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static function (OperationContext $ctx, OperationStartDetails $details, ?string $reason): string {
                throw HandlerException::create(ErrorType::Unauthorized, "deny: {$reason}");
            },
        );
    }

    #[OperationImpl]
    public function notFoundOp(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static function (OperationContext $ctx, OperationStartDetails $details, ?string $reason): string {
                throw HandlerException::create(ErrorType::NotFound, "missing: {$reason}");
            },
        );
    }

    #[OperationImpl]
    public function cancelOp(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static function (OperationContext $ctx, OperationStartDetails $details, ?string $reason): string {
                throw OperationException::canceled($reason ?? 'canceled');
            },
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
