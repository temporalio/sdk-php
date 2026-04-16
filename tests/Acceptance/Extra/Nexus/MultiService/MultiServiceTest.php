<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\MultiService;

use Nexus\Sdk\Attribute\Operation;
use Nexus\Sdk\Attribute\OperationImpl;
use Nexus\Sdk\Attribute\Service;
use Nexus\Sdk\Attribute\ServiceImpl;
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
 * Acceptance test: a single worker hosts multiple Nexus services.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class MultiServiceTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function bothServicesAreInvokable(
        State $state,
        #[Stub('Extra_Nexus_MultiService_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\MultiService',
            'test-nexus-multi',
        );

        // Service A
        [$codeA, $respA] = $helper->postOperation($endpointId, 'ServiceA', 'opA', 'foo');
        self::assertSame(200, $codeA, "ServiceA: expected 200, got {$codeA}. Response: {$respA}");
        self::assertStringContainsString('A:foo', $respA);

        // Service B
        [$codeB, $respB] = $helper->postOperation($endpointId, 'ServiceB', 'opB', 'bar');
        self::assertSame(200, $codeB, "ServiceB: expected 200, got {$codeB}. Response: {$respB}");
        self::assertStringContainsString('B:bar', $respB);
    }
}

#[Service(name: 'ServiceA')]
interface ServiceAInterface
{
    #[Operation]
    public function opA(string $input): string;
}

#[ServiceImpl(service: ServiceAInterface::class)]
class ServiceAImpl
{
    #[OperationImpl]
    public function opA(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $ctx, OperationStartDetails $details, ?string $input): string
                => "A:{$input}",
        );
    }
}

#[Service(name: 'ServiceB')]
interface ServiceBInterface
{
    #[Operation]
    public function opB(string $input): string;
}

#[ServiceImpl(service: ServiceBInterface::class)]
class ServiceBImpl
{
    #[OperationImpl]
    public function opB(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $ctx, OperationStartDetails $details, ?string $input): string
                => "B:{$input}",
        );
    }
}

#[WorkflowInterface]
class MultiServiceBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_MultiService_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}
