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
    public function operationFailureReturnsErrorResponse(
        State $state,
        #[Stub('Extra_Nexus_Errors_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $this->setupEndpoint($helper, $state->namespace);

        [$code, $resp] = $helper->postOperation($endpointId, 'ErrorService', 'failOp', 'business-error');

        // OperationException → 4xx-class error; Temporal returns failure JSON
        self::assertNotSame(200, $code, "Expected error response, got 200. Body: {$resp}");
        self::assertStringContainsString('business-error', $resp, 'Failure message should be propagated');
    }

    #[Test]
    public function handlerErrorReturnsErrorResponse(
        State $state,
        #[Stub('Extra_Nexus_Errors_Bootstrap2')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $this->setupEndpoint($helper, $state->namespace);

        [$code, $resp] = $helper->postOperation($endpointId, 'ErrorService', 'handlerErrorOp', 'infra');

        // HandlerException → also error response from Temporal
        self::assertNotSame(200, $code, "Expected error response, got 200. Body: {$resp}");
    }

    #[Test]
    public function unknownOperationReturnsError(
        State $state,
        #[Stub('Extra_Nexus_Errors_Bootstrap3')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $this->setupEndpoint($helper, $state->namespace);

        [$code, $resp] = $helper->postOperation($endpointId, 'ErrorService', 'nonExistentOp', 'whatever');

        self::assertNotSame(200, $code, "Expected error for unknown operation, got 200. Body: {$resp}");
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
