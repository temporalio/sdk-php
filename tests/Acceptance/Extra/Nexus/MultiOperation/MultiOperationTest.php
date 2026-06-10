<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\MultiOperation;

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
 * Acceptance test: a single Nexus service with multiple operations.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class MultiOperationTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function multipleOperationsAreInvokable(
        State $state,
        NexusEndpoints $endpoints,
        NexusHttpClient $http,
        #[Stub('Extra_Nexus_MultiOp_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__);

        $cases = [
            ['add', [3, 5], '8'],
            ['multiply', [4, 7], '28'],
            ['echo', ['hello'], '"hello"'],
            ['constant', [null], '42'],
        ];

        foreach ($cases as [$op, $body, $expectedFragment]) {
            $payload = \count($body) === 1 ? $body[0] : $body;
            [$code, $resp, ] = $http->post($endpoint, 'MathService', $op, $payload);
            self::assertSame(200, $code, "Operation {$op}: expected 200, got {$code}. Response: {$resp}");
            self::assertStringContainsString($expectedFragment, $resp, "Operation {$op}: missing expected fragment");
        }
    }
}

#[Service(name: 'MathService')]
class MathService
{
    #[Operation]
    public function add(array $args): int
    {
        return (int) ($args[0] ?? 0) + (int) ($args[1] ?? 0);
    }

    #[Operation]
    public function multiply(array $args): int
    {
        return (int) ($args[0] ?? 1) * (int) ($args[1] ?? 1);
    }

    #[Operation]
    public function echo(string $value): string
    {
        return $value;
    }

    #[Operation]
    public function constant(): int
    {
        return 42;
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
