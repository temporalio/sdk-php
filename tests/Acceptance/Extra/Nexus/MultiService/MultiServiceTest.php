<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\MultiService;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHttpClient;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusWorkerOptions;
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
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function bothServicesAreInvokable(
        State $state,
        NexusEndpoints $endpoints,
        NexusHttpClient $http,
        #[Stub('Extra_Nexus_MultiService_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'test-nexus-multi');

        [$codeA, $respA, ] = $http->post($endpoint, 'ServiceA', 'opA', 'foo');
        self::assertSame(200, $codeA, "ServiceA: expected 200, got {$codeA}. Response: {$respA}");
        self::assertStringContainsString('A:foo', $respA);

        [$codeB, $respB, ] = $http->post($endpoint, 'ServiceB', 'opB', 'bar');
        self::assertSame(200, $codeB, "ServiceB: expected 200, got {$codeB}. Response: {$respB}");
        self::assertStringContainsString('B:bar', $respB);
    }
}

#[Service(name: 'ServiceA')]
class ServiceA
{
    #[Operation]
    public function opA(string $input): string
    {
        return "A:{$input}";
    }
}

#[Service(name: 'ServiceB')]
class ServiceB
{
    #[Operation]
    public function opB(string $input): string
    {
        return "B:{$input}";
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
