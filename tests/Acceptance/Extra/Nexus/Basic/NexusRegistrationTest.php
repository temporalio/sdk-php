<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Basic;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
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

// Interface + impl shape kept here on purpose: this is the smoke test for the
// "contract is an interface, impl class implements it" registration path. The
// rest of the Nexus acceptance suite covers the class-only `#[Service]` shape.
#[Service(name: 'GreetingService')]
interface GreetingNexusServiceInterface
{
    #[Operation]
    public function greet(string $name): string;
}

final class GreetingNexusServiceImpl implements GreetingNexusServiceInterface
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
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
