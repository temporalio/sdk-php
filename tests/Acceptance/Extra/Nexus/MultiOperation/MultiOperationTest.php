<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\MultiOperation;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\OperationImpl;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Attribute\ServiceImpl;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\SynchronousOperationHandler;
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
 * Acceptance test: a single Nexus service with multiple operations.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class MultiOperationTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function multipleOperationsAreInvokable(
        State $state,
        #[Stub('Extra_Nexus_MultiOp_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        // Wait for worker to register
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\MultiOperation',
        );

        $cases = [
            ['add', [3, 5], '8'],
            ['multiply', [4, 7], '28'],
            ['echo', ['hello'], '"hello"'],
            ['constant', [null], '42'],
        ];

        foreach ($cases as [$op, $body, $expectedFragment]) {
            // For ops with multiple args we just send the array
            $payload = \count($body) === 1 ? $body[0] : $body;
            [$code, $resp] = $helper->postOperation($endpointId, 'MathService', $op, $payload);
            self::assertSame(200, $code, "Operation {$op}: expected 200, got {$code}. Response: {$resp}");
            self::assertStringContainsString($expectedFragment, $resp, "Operation {$op}: missing expected fragment");
        }
    }
}

#[Service(name: 'MathService')]
interface MathServiceInterface
{
    #[Operation]
    public function add(array $args): int;

    #[Operation]
    public function multiply(array $args): int;

    #[Operation]
    public function echo(string $value): string;

    #[Operation]
    public function constant(): int;
}

#[ServiceImpl(service: MathServiceInterface::class)]
class MathServiceImpl
{
    #[OperationImpl]
    public function add(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $ctx, OperationStartDetails $details, ?array $args): int
                => (int) ($args[0] ?? 0) + (int) ($args[1] ?? 0),
        );
    }

    #[OperationImpl]
    public function multiply(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $ctx, OperationStartDetails $details, ?array $args): int
                => (int) ($args[0] ?? 1) * (int) ($args[1] ?? 1),
        );
    }

    #[OperationImpl]
    public function echo(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $ctx, OperationStartDetails $details, ?string $value): string
                => $value ?? '',
        );
    }

    #[OperationImpl]
    public function constant(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $ctx, OperationStartDetails $details, mixed $_): int => 42,
        );
    }
}

#[WorkflowInterface]
class MultiOpBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_MultiOp_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}
