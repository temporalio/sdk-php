<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Headers;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
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
 * Acceptance test: HTTP request headers are propagated to the Nexus handler.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class HeadersTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function customHeadersArePropagated(
        State $state,
        #[Stub('Extra_Nexus_Headers_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Headers',
            'test-nexus-hdr',
        );

        [$code, $resp] = $helper->postOperation(
            $endpointId,
            'HeaderEchoService',
            'echoHeader',
            'X-Custom-Header',
            ['X-Custom-Header' => 'my-test-value'],
        );

        self::assertSame(200, $code, "Expected 200, got {$code}. Response: {$resp}");
        self::assertStringContainsString('my-test-value', $resp, 'Expected header value in response');
    }
}

#[Service(name: 'HeaderEchoService')]
interface HeaderEchoServiceInterface
{
    #[Operation]
    public function echoHeader(string $headerName): string;
}

class HeaderEchoServiceImpl implements HeaderEchoServiceInterface
{
    public function echoHeader(string $headerName): string
    {
        if ($headerName === '') {
            return '';
        }
        // Headers in OperationContext are case-insensitive (lowercased)
        $context = Nexus::getCurrentContext();
        $key = \strtolower($headerName);
        return $context->headers[$key] ?? "missing:{$headerName}";
    }
}

#[WorkflowInterface]
class HeadersBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Headers_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}
