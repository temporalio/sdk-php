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
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
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
        $host = \parse_url("http://{$state->address}", PHP_URL_HOST) ?? '127.0.0.1';
        $taskQueue = 'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Basic';
        $endpointName = 'test-nexus-ep-' . \bin2hex(\random_bytes(4));

        // Step 1: Create Nexus endpoint → worker's task queue
        $created = $this->createNexusEndpoint($endpointName, $state->namespace, $taskQueue, $state->address);
        if (!$created) {
            self::markTestSkipped('Could not create Nexus endpoint');
        }

        // Step 2: Get endpoint UUID (Temporal HTTP API uses endpoint UUID, not name)
        $endpointId = $this->getNexusEndpointId($endpointName, $state->address);
        if ($endpointId === null) {
            self::markTestSkipped('Could not resolve endpoint UUID');
        }

        // Step 3: POST to Temporal HTTP API (port 7243)
        // Path: /nexus/endpoints/{endpoint-uuid}/services/{service}/{operation}
        $url = "http://{$host}:7243/nexus/endpoints/{$endpointId}/services/GreetingService/greet";

        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => \json_encode('World'),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = \curl_exec($ch);
        $httpCode = (int) \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);

        if ($httpCode === 0) {
            self::markTestSkipped('Temporal HTTP API not reachable on port 7243');
        }

        // Step 3: Verify result
        self::assertSame(200, $httpCode, "Expected HTTP 200, got {$httpCode}. Response: " . \substr((string)$response, 0, 500));
        self::assertStringContainsString('Hello, World!', (string) $response);
    }

    private function getNexusEndpointId(string $name, string $address): ?string
    {
        $temporal = \getenv('TEMPORAL_CLI') ?: './temporal';

        $process = new \Symfony\Component\Process\Process([
            $temporal,
            'operator', 'nexus', 'endpoint', 'list',
            '--address', $address,
            '--output', 'json',
        ]);
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $output = $process->getOutput();
        // Parse JSON array of endpoints
        try {
            $data = \json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!\is_array($data)) {
            return null;
        }

        foreach ($data as $entry) {
            $epName = $entry['endpoint']['spec']['name'] ?? $entry['spec']['name'] ?? null;
            $epId = $entry['endpoint']['id'] ?? $entry['id'] ?? null;
            if ($epName === $name && \is_string($epId)) {
                return $epId;
            }
        }

        return null;
    }

    private function createNexusEndpoint(string $name, string $namespace, string $taskQueue, string $address): bool
    {
        $temporal = \getenv('TEMPORAL_CLI') ?: './temporal';

        $process = new \Symfony\Component\Process\Process([
            $temporal,
            'operator', 'nexus', 'endpoint', 'create',
            '--name', $name,
            '--target-namespace', $namespace,
            '--target-task-queue', $taskQueue,
            '--address', $address,
        ]);
        $process->setTimeout(10);

        try {
            $process->run();
            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
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
