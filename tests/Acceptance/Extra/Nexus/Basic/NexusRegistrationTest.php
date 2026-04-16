<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Basic;

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
 * Acceptance test: full Nexus handler round-trip through Temporal server.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class NexusRegistrationTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function workerBootsWithNexusService(
        #[Stub('Extra_Nexus_Basic')]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult('string');

        self::assertSame('nexus-service-registered', $result);
    }

    #[Test]
    public function nexusHandlerProcessesRequest(
        State $state,
    ): void {
        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Basic',
        );

        [$code, $resp] = $helper->postOperation($endpointId, 'GreetingService', 'greet', 'World');

        self::assertSame(200, $code, "Expected HTTP 200, got {$code}. Response: " . \substr($resp, 0, 500));
        self::assertStringContainsString('Hello, World!', $resp);
    }
}

// ── Nexus service (handler side) ─────────────────────────────────

#[Service(name: 'GreetingService')]
interface GreetingNexusServiceInterface
{
    #[Operation]
    public function greet(string $name): string;
}

#[ServiceImpl(service: GreetingNexusServiceInterface::class)]
class GreetingNexusServiceImpl
{
    #[OperationImpl]
    public function greet(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $ctx, OperationStartDetails $details, ?string $name): string
                => "Hello, {$name}!",
        );
    }
}

// ── Workflow (needed for the test framework) ─────────────────────

#[WorkflowInterface]
class NexusBasicWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Basic')]
    public function run(): string
    {
        return 'nexus-service-registered';
    }
}
