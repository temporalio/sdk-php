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
